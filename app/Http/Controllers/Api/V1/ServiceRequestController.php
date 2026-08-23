<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ServiceRequestResource;
use App\Models\DiagnosticReport;
use App\Models\Mechanic;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestMessage;
use App\Models\Vehicle;
use App\Services\Notifications\UserNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRequestController
{
    public function index(Request $request)
    {
        $data = $request->validate(['cursor' => ['nullable', 'string', 'max:512'], 'limit' => ['nullable', 'integer', 'between:1,50']]);
        $page = ServiceRequest::query()->where('user_id', $request->user()->id)
            ->with($this->relations())->latest('id')
            ->cursorPaginate($data['limit'] ?? 20, ['*'], 'cursor', $data['cursor'] ?? null);

        return ApiResponse::success(ServiceRequestResource::collection($page->items())->resolve(), 200, ['nextCursor' => $page->nextCursor()?->encode()]);
    }

    public function mechanicIndex(Request $request)
    {
        $mechanicIds = Mechanic::query()->where('owner_user_id', $request->user()->id)->pluck('id');
        $items = ServiceRequest::query()->whereHas('mechanics', fn ($query) => $query->whereIn('mechanics.id', $mechanicIds))
            ->with($this->relations())->latest()->limit(100)->get();

        return ApiResponse::success(ServiceRequestResource::collection($items)->resolve());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicleId' => ['required', 'ulid'],
            'reportId' => ['nullable', 'ulid'],
            'mechanicIds' => ['required', 'array', 'between:1,3'],
            'mechanicIds.*' => ['required', 'ulid', 'distinct'],
            'description' => ['nullable', 'string', 'max:3000'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
        ]);
        $vehicle = Vehicle::query()->where('user_id', $request->user()->id)->findOrFail($data['vehicleId']);
        if (isset($data['reportId'])) {
            DiagnosticReport::query()->where('user_id', $request->user()->id)
                ->where('vehicle_id', $vehicle->id)->findOrFail($data['reportId']);
        }
        $mechanics = Mechanic::query()->whereIn('id', $data['mechanicIds'])
            ->where('active', true)->where('verified', true)->get();
        if ($mechanics->count() !== count($data['mechanicIds'])) {
            throw ValidationException::withMessages(['mechanicIds' => [__('api.service_request_mechanics_invalid')]]);
        }
        $key = trim((string) $request->header('Idempotency-Key'));
        $key = $key === '' ? null : mb_substr($key, 0, 128);
        if ($key && $existing = ServiceRequest::query()->where('user_id', $request->user()->id)->where('idempotency_key', $key)->first()) {
            return ApiResponse::success((new ServiceRequestResource($existing->load($this->relations())))->resolve());
        }
        $serviceRequest = DB::transaction(function () use ($request, $data, $key, $mechanics) {
            $created = ServiceRequest::query()->create([
                'user_id' => $request->user()->id,
                'vehicle_id' => $data['vehicleId'],
                'diagnostic_report_id' => $data['reportId'] ?? null,
                'status' => 'requested',
                'description' => $data['description'] ?? null,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
                'idempotency_key' => $key,
            ]);
            $created->mechanics()->attach($mechanics->pluck('id')->all(), ['status' => 'invited']);

            return $created;
        });

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->load($this->relations())))->resolve(), 201);
    }

    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeParticipant($request, $serviceRequest);

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->load($this->relations())))->resolve());
    }

    public function message(Request $request, ServiceRequest $serviceRequest, UserNotificationService $notifications)
    {
        $mechanic = $this->authorizeParticipant($request, $serviceRequest);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $message = ServiceRequestMessage::query()->create([
            'service_request_id' => $serviceRequest->id,
            'sender_user_id' => $request->user()->id,
            'mechanic_id' => $mechanic?->id,
            'sender_role' => $mechanic ? 'mechanic' : 'customer',
            'body' => $data['body'],
        ]);
        if ($mechanic) {
            $notifications->send(
                $serviceRequest->user,
                'service_request_message',
                'New workshop message', 'رسالة جديدة من الورشة',
                'A workshop replied to your service request.', 'ردّت ورشة على طلب الصيانة الخاص بك.',
                ['serviceRequestId' => (string) $serviceRequest->id],
            );
        }

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->fresh()->load($this->relations())))->resolve(), 201);
    }

    public function quote(Request $request, ServiceRequest $serviceRequest, UserNotificationService $notifications)
    {
        $mechanic = $this->ownedInvitedMechanic($request, $serviceRequest);
        if (! in_array($serviceRequest->status, ['requested', 'quotes_ready'], true)) {
            return ApiResponse::error('SERVICE_QUOTE_UNAVAILABLE', __('api.service_quote_unavailable'), 409);
        }
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'laborAmount' => ['required', 'decimal:0,2', 'min:0'],
            'partsAmount' => ['required', 'decimal:0,2', 'min:0'],
            'feesAmount' => ['nullable', 'decimal:0,2', 'min:0'],
            'estimatedDurationMinutes' => ['nullable', 'integer', 'between:15,43200'],
            'warrantyText' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lineItems' => ['nullable', 'array', 'max:50'],
            'lineItems.*.label' => ['required_with:lineItems', 'string', 'max:200'],
            'lineItems.*.category' => ['required_with:lineItems', 'in:labor,part,fee,other'],
            'lineItems.*.amount' => ['required_with:lineItems', 'decimal:0,2', 'min:0'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ]);
        $fees = (string) ($data['feesAmount'] ?? '0');
        $total = bcadd(bcadd((string) $data['laborAmount'], (string) $data['partsAmount'], 2), $fees, 2);
        $quote = ServiceQuote::query()->updateOrCreate(
            ['service_request_id' => $serviceRequest->id, 'mechanic_id' => $mechanic->id],
            [
                'status' => 'submitted', 'currency' => strtoupper($data['currency']),
                'labor_amount' => $data['laborAmount'], 'parts_amount' => $data['partsAmount'],
                'fees_amount' => $fees, 'total_amount' => $total,
                'estimated_duration_minutes' => $data['estimatedDurationMinutes'] ?? null,
                'warranty_text' => $data['warrantyText'] ?? null, 'notes' => $data['notes'] ?? null,
                'line_items_json' => $data['lineItems'] ?? null, 'expires_at' => $data['expiresAt'] ?? null,
            ],
        );
        $serviceRequest->mechanics()->updateExistingPivot($mechanic->id, ['status' => 'responded']);
        $serviceRequest->update(['status' => 'quotes_ready']);
        $notifications->send(
            $serviceRequest->user,
            'service_quote_received',
            'A workshop sent a quote', 'أرسلت ورشة عرض سعر',
            'Compare the itemized quote in AutoMind.', 'قارن عرض السعر المفصل داخل أوتومايند.',
            ['serviceRequestId' => (string) $serviceRequest->id, 'quoteId' => (string) $quote->id],
        );

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->fresh()->load($this->relations())))->resolve(), $quote->wasRecentlyCreated ? 201 : 200);
    }

    public function accept(Request $request, ServiceRequest $serviceRequest, ServiceQuote $quote)
    {
        $this->authorizeOwner($request, $serviceRequest);
        if ($quote->service_request_id !== $serviceRequest->id || $quote->status !== 'submitted' || ($quote->expires_at && $quote->expires_at->isPast())) {
            return ApiResponse::error('SERVICE_QUOTE_UNAVAILABLE', __('api.service_quote_unavailable'), 409);
        }
        DB::transaction(function () use ($serviceRequest, $quote): void {
            ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            ServiceQuote::query()->where('service_request_id', $serviceRequest->id)->whereKeyNot($quote->id)->update(['status' => 'declined']);
            $quote->update(['status' => 'accepted']);
            $serviceRequest->update(['selected_quote_id' => $quote->id, 'status' => 'accepted']);
        });

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->fresh()->load($this->relations())))->resolve());
    }

    public function updateStatus(Request $request, ServiceRequest $serviceRequest, UserNotificationService $notifications)
    {
        $mechanic = $this->ownedInvitedMechanic($request, $serviceRequest);
        $selectedMechanicId = $serviceRequest->selectedQuote?->mechanic_id;
        if ($selectedMechanicId === null || $selectedMechanicId !== $mechanic->id) {
            abort(403);
        }
        $data = $request->validate(['status' => ['required', 'in:in_service,completed']]);
        $allowed = ($serviceRequest->status === 'accepted' && $data['status'] === 'in_service')
            || ($serviceRequest->status === 'in_service' && $data['status'] === 'completed');
        if (! $allowed) {
            return ApiResponse::error('SERVICE_REQUEST_TRANSITION_INVALID', __('api.service_request_transition_invalid'), 409);
        }
        $serviceRequest->update(['status' => $data['status']]);
        $notifications->send(
            $serviceRequest->user,
            'service_request_status',
            'Repair status updated', 'تم تحديث حالة الإصلاح',
            'Open AutoMind to view the latest service status.', 'افتح أوتومايند لعرض أحدث حالة للصيانة.',
            ['serviceRequestId' => (string) $serviceRequest->id],
        );

        return ApiResponse::success((new ServiceRequestResource($serviceRequest->fresh()->load($this->relations())))->resolve());
    }

    private function authorizeOwner(Request $request, ServiceRequest $serviceRequest): void
    {
        abort_unless($serviceRequest->user_id === $request->user()->id, 403);
    }

    private function authorizeParticipant(Request $request, ServiceRequest $serviceRequest): ?Mechanic
    {
        if ($serviceRequest->user_id === $request->user()->id) {
            return null;
        }

        return $this->ownedInvitedMechanic($request, $serviceRequest);
    }

    private function ownedInvitedMechanic(Request $request, ServiceRequest $serviceRequest): Mechanic
    {
        $mechanic = Mechanic::query()->where('owner_user_id', $request->user()->id)
            ->whereHas('serviceRequests', fn ($query) => $query->whereKey($serviceRequest->id))->first();
        if ($mechanic === null) {
            abort(403);
        }

        return $mechanic;
    }

    private function relations(): array
    {
        return ['vehicle', 'report.translations', 'mechanics', 'quotes.mechanic', 'selectedQuote.mechanic', 'messages.mechanic'];
    }
}
