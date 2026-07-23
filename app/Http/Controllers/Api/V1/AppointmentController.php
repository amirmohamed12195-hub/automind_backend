<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\DiagnosticReport;
use App\Models\Mechanic;
use App\Models\MechanicReview;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AppointmentController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $page = Appointment::query()->where('user_id', $request->user()->id)->with(['mechanic', 'vehicle'])->latest('id')->cursorPaginate($data['limit'] ?? 20, ['*'], 'cursor', $data['cursor'] ?? null);

        return ApiResponse::success(AppointmentResource::collection($page->items())->resolve(), 200, ['nextCursor' => $page->nextCursor()?->encode()]);
    }

    public function store(AppointmentRequest $request)
    {
        $vehicle = Vehicle::query()->where('user_id', $request->user()->id)->findOrFail($request->input('vehicleId'));
        $mechanic = Mechanic::query()->where('active', true)->where('verified', true)->findOrFail($request->input('mechanicId'));
        if ($request->filled('reportId')) {
            DiagnosticReport::query()->where('user_id', $request->user()->id)->where('vehicle_id', $vehicle->id)->findOrFail($request->input('reportId'));
        }
        $key = trim((string) $request->header('Idempotency-Key'));
        $key = $key === '' ? null : mb_substr($key, 0, 128);
        $start = CarbonImmutable::parse($request->input('requestedStart'))->utc();
        $end = CarbonImmutable::parse($request->input('requestedEnd'))->utc();
        if ($key && $existing = Appointment::query()->where('user_id', $request->user()->id)->where('idempotency_key', $key)->first()) {
            return ApiResponse::success((new AppointmentResource($existing))->resolve());
        }
        $appointment = DB::transaction(function () use ($request, $vehicle, $mechanic, $key, $start, $end) {
            Mechanic::query()->whereKey($mechanic->id)->lockForUpdate()->firstOrFail();
            $conflict = Appointment::query()->where('mechanic_id', $mechanic->id)->whereIn('status', ['requested', 'confirmed'])->where('requested_start_at', '<', $end)->where('requested_end_at', '>', $start)->exists();
            if ($conflict) {
                return null;
            }

            return Appointment::query()->create(['user_id' => $request->user()->id, 'mechanic_id' => $mechanic->id, 'vehicle_id' => $vehicle->id, 'diagnostic_report_id' => $request->input('reportId'), 'requested_start_at' => $start, 'requested_end_at' => $end, 'status' => 'requested', 'customer_note' => $request->input('customerNote'), 'idempotency_key' => $key]);
        });
        if (! $appointment) {
            return ApiResponse::error('APPOINTMENT_CONFLICT', __('api.appointment_conflict'), 409);
        }

        return ApiResponse::success((new AppointmentResource($appointment))->resolve(), 201);
    }

    public function show(Appointment $appointment)
    {
        Gate::authorize('view', $appointment);

        return ApiResponse::success((new AppointmentResource($appointment->load(['mechanic', 'vehicle'])))->resolve());
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);
        if (! in_array($appointment->status, ['requested', 'confirmed'], true)) {
            return ApiResponse::error('APPOINTMENT_IMMUTABLE', __('api.appointment_immutable'), 409);
        } $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $appointment->update(['status' => 'cancelled', 'cancellation_reason' => $data['reason'], 'cancelled_at' => now()]);

        return ApiResponse::success((new AppointmentResource($appointment->fresh()))->resolve());
    }

    public function review(Request $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);
        if ($appointment->status !== 'completed') {
            return ApiResponse::error('APPOINTMENT_NOT_COMPLETED', __('api.appointment_not_completed'), 409);
        } $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $review = DB::transaction(function () use ($appointment, $request, $data) {
            Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($existing = MechanicReview::query()->where('appointment_id', $appointment->id)->first()) {
                return $existing;
            }
            $review = MechanicReview::query()->create(['appointment_id' => $appointment->id, 'user_id' => $request->user()->id, 'mechanic_id' => $appointment->mechanic_id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'moderation_status' => 'pending']);
            $stats = MechanicReview::query()->where('mechanic_id', $appointment->mechanic_id)->where('moderation_status', 'approved')->selectRaw('AVG(rating) average, COUNT(*) count')->first();
            Mechanic::query()->whereKey($appointment->mechanic_id)->update(['rating_average' => $stats->average ?? 0, 'rating_count' => $stats->count ?? 0]);

            return $review;
        });

        return ApiResponse::success(['id' => (string) $review->id, 'rating' => (int) $review->rating, 'moderationStatus' => $review->moderation_status], $review->wasRecentlyCreated ? 201 : 200);
    }
}
