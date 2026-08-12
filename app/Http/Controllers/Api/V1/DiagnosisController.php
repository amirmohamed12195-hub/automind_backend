<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DiagnosticStatus;
use App\Enums\DiagnosticStep;
use App\Http\Requests\CreateDiagnosisRequest;
use App\Http\Requests\UpdateDiagnosisRequest;
use App\Http\Resources\DiagnosticReportResource;
use App\Http\Resources\DiagnosticSessionResource;
use App\Jobs\AnalyzeDiagnosticSession;
use App\Models\DiagnosticSession;
use App\Models\SymptomDefinition;
use App\Models\Vehicle;
use App\Services\Billing\ReportEntitlementService;
use App\Services\Diagnostics\DiagnosticStateMachine;
use App\Services\PlatformSettings;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DiagnosisController
{
    public function symptoms()
    {
        $locale = app()->getLocale();

        return ApiResponse::success(SymptomDefinition::query()->where('active', true)->orderBy('sort_order')->get()->map(fn ($s) => ['code' => $s->code, 'label' => $s->{"label_$locale"}])->all());
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $page = DiagnosticSession::query()->where('user_id', $request->user()->id)->with('symptoms')->withCount(['media', 'obdSnapshots'])->latest('id')->cursorPaginate($data['limit'] ?? 20, ['*'], 'cursor', $data['cursor'] ?? null);

        return ApiResponse::success(DiagnosticSessionResource::collection($page->items())->resolve(), 200, ['nextCursor' => $page->nextCursor()?->encode()]);
    }

    public function store(CreateDiagnosisRequest $request)
    {
        $settings = app(PlatformSettings::class);
        $vehicle = Vehicle::query()->where('user_id', $request->user()->id)->findOrFail($request->input('vehicleId'));
        $description = trim((string) $request->input('description'));
        if ($description === '' && $request->input('selectedSymptoms', []) === []) {
            return ApiResponse::error('EVIDENCE_REQUIRED', __('api.no_evidence'), 422);
        }
        $idempotencyKey = $this->idempotencyKey($request);
        if ($idempotencyKey && $existing = DiagnosticSession::query()->where('user_id', $request->user()->id)->where('idempotency_key', $idempotencyKey)->first()) {
            return ApiResponse::success((new DiagnosticSessionResource($existing->load('symptoms')->loadCount(['media', 'obdSnapshots'])))->resolve());
        }
        $session = DB::transaction(function () use ($request, $vehicle, $idempotencyKey, $description, $settings) {
            $session = DiagnosticSession::query()->create([
                'user_id' => $request->user()->id, 'vehicle_id' => $vehicle->id, 'status' => DiagnosticStatus::Draft->value,
                'description' => $description ?: null, 'input_locale' => $request->input('inputLocale'), 'report_locale' => $request->input('reportLocale'),
                'market_country_code' => strtoupper((string) $request->input('market.countryCode', $request->user()->country_code ?: $settings->get('default_country'))),
                'market_city' => $request->input('market.city', $request->user()->city), 'market_currency' => strtoupper((string) $request->input('market.currency', $request->user()->currency ?: $settings->get('default_currency'))),
                'client_reference' => $request->input('clientReference'), 'idempotency_key' => $idempotencyKey,
                'current_step' => DiagnosticStep::PreparingData->value, 'prompt_version' => config('automind.diagnostic_prompt_version'),
                'consent_version' => $request->input('consentVersion'), 'consented_at' => now(),
            ]);
            $ids = SymptomDefinition::query()->whereIn('code', $request->input('selectedSymptoms', []))->pluck('id');
            $session->symptoms()->sync($ids);

            return $session;
        });

        return ApiResponse::success((new DiagnosticSessionResource($session->load('symptoms')->loadCount(['media', 'obdSnapshots'])))->resolve(), 201);
    }

    public function show(DiagnosticSession $diagnosis)
    {
        Gate::authorize('view', $diagnosis);

        return ApiResponse::success((new DiagnosticSessionResource($diagnosis->load('symptoms')->loadCount(['media', 'obdSnapshots'])))->resolve());
    }

    public function update(UpdateDiagnosisRequest $request, DiagnosticSession $diagnosis)
    {
        Gate::authorize('update', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'uploading'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $description = $request->exists('description') ? trim((string) $request->input('description')) : $diagnosis->description;
        $symptoms = $request->exists('selectedSymptoms') ? $request->input('selectedSymptoms', []) : $diagnosis->symptoms()->pluck('code')->all();
        if (! $description && $symptoms === [] && $diagnosis->media()->whereNull('deleted_at')->count() === 0 && $diagnosis->obdSnapshots()->count() === 0) {
            return ApiResponse::error('EVIDENCE_REQUIRED', __('api.no_evidence'), 422);
        }
        $updates = [];
        if ($request->exists('description')) {
            $updates['description'] = $description ?: null;
        }
        if ($request->exists('reportLocale')) {
            $updates['report_locale'] = $request->input('reportLocale');
        }
        DB::transaction(function () use ($diagnosis, $request, $updates, $symptoms): void {
            $diagnosis->update($updates);
            if ($request->exists('selectedSymptoms')) {
                $diagnosis->symptoms()->sync(SymptomDefinition::query()->whereIn('code', $symptoms)->pluck('id'));
            }
        });

        return ApiResponse::success((new DiagnosticSessionResource($diagnosis->fresh()->load('symptoms')->loadCount(['media', 'obdSnapshots'])))->resolve());
    }

    public function destroy(DiagnosticSession $diagnosis)
    {
        Gate::authorize('delete', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'failed', 'cancelled'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $diagnosis->delete();

        return response()->noContent();
    }

    public function analyze(Request $request, DiagnosticSession $diagnosis, DiagnosticStateMachine $stateMachine, ReportEntitlementService $reportEntitlements)
    {
        Gate::authorize('update', $diagnosis);
        if ($diagnosis->status === 'queued') {
            $redispatchAfter = max(30, (int) config('automind.queue.diagnostic_redispatch_after_seconds', 90));
            if ($diagnosis->updated_at?->lte(now()->subSeconds($redispatchAfter))) {
                $diagnosis->touch();
                AnalyzeDiagnosticSession::dispatch($diagnosis->id)->afterCommit();
            }

            return $this->accepted($diagnosis->fresh());
        }
        if ($diagnosis->status === 'analyzing') {
            return $this->accepted($diagnosis);
        }
        if (! in_array($diagnosis->status, ['draft', 'uploading', 'failed'], true)) {
            return ApiResponse::error('INVALID_DIAGNOSTIC_STATE', __('api.invalid_transition'), 409);
        }
        if ($diagnosis->media()->whereNull('deleted_at')->where('processing_status', '!=', 'ready')->exists()) {
            return ApiResponse::error('MEDIA_NOT_READY', __('api.media_not_ready'), 409);
        }
        $hasEvidence = (bool) $diagnosis->description || $diagnosis->symptoms()->exists() || $diagnosis->media()->whereNull('deleted_at')->exists() || $diagnosis->obdSnapshots()->exists();
        if (! $hasEvidence) {
            return ApiResponse::error('EVIDENCE_REQUIRED', __('api.no_evidence'), 422);
        }
        $diagnosis = DB::transaction(function () use ($diagnosis, $reportEntitlements, $stateMachine): DiagnosticSession {
            $reportEntitlements->reserve($diagnosis);

            return $stateMachine->transition($diagnosis->fresh(), DiagnosticStatus::Queued, ['progress_percentage' => 0, 'current_step' => DiagnosticStep::PreparingData->value, 'error_code' => null, 'safe_error_message' => null, 'failed_at' => null]);
        });
        AnalyzeDiagnosticSession::dispatch($diagnosis->id)->afterCommit();

        return $this->accepted($diagnosis);
    }

    public function cancel(DiagnosticSession $diagnosis, DiagnosticStateMachine $stateMachine, ReportEntitlementService $reportEntitlements)
    {
        Gate::authorize('update', $diagnosis);
        if (in_array($diagnosis->status, ['completed', 'cancelled'], true)) {
            return ApiResponse::error('INVALID_DIAGNOSTIC_STATE', __('api.invalid_transition'), 409);
        }
        $diagnosis = DB::transaction(function () use ($diagnosis, $stateMachine, $reportEntitlements): DiagnosticSession {
            $cancelled = $stateMachine->transition($diagnosis, DiagnosticStatus::Cancelled, ['cancelled_at' => now()]);
            $reportEntitlements->release($cancelled);

            return $cancelled;
        });

        return ApiResponse::success((new DiagnosticSessionResource($diagnosis->load('symptoms')->loadCount(['media', 'obdSnapshots'])))->resolve());
    }

    public function retry(Request $request, DiagnosticSession $diagnosis, DiagnosticStateMachine $stateMachine, ReportEntitlementService $reportEntitlements)
    {
        return $this->analyze($request, $diagnosis, $stateMachine, $reportEntitlements);
    }

    public function status(DiagnosticSession $diagnosis)
    {
        Gate::authorize('view', $diagnosis);

        return ApiResponse::success(['sessionId' => (string) $diagnosis->id, 'status' => $diagnosis->status, 'progress' => (int) $diagnosis->progress_percentage, 'currentStep' => $diagnosis->current_step, 'error' => $diagnosis->error_code ? ['code' => $diagnosis->error_code, 'message' => trans()->has("api.diagnostic_errors.$diagnosis->error_code") ? __("api.diagnostic_errors.$diagnosis->error_code") : $diagnosis->safe_error_message] : null, 'reportId' => $diagnosis->report?->id]);
    }

    public function report(DiagnosticSession $diagnosis)
    {
        Gate::authorize('view', $diagnosis);
        if ($diagnosis->status !== 'completed' || ! $diagnosis->report) {
            return ApiResponse::error('REPORT_NOT_READY', __('api.report_not_ready'), 409);
        }

        return ApiResponse::success((new DiagnosticReportResource($diagnosis->report->load($this->reportRelations())))->resolve(), 200, ['locale' => app()->getLocale()]);
    }

    private function accepted(DiagnosticSession $diagnosis)
    {
        return ApiResponse::success(['sessionId' => (string) $diagnosis->id, 'status' => $diagnosis->status, 'progress' => (int) $diagnosis->progress_percentage, 'currentStep' => $diagnosis->current_step, 'statusUrl' => "/api/v1/diagnoses/{$diagnosis->id}/status"], 202);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        return $key !== '' ? mb_substr($key, 0, 128) : null;
    }

    private function reportRelations(): array
    {
        return ['vehicle', 'translations', 'faults.translations', 'faults.causes.translations', 'faults.actions.translations', 'faults.parts.translations', 'faults.evidence', 'actions.translations', 'evidence', 'estimate.lineItems', 'priceSearches.sources'];
    }
}
