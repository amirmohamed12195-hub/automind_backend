<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DiagnosticReportResource;
use App\Jobs\RefreshServiceEstimate;
use App\Models\DiagnosticReport;
use App\Models\ReportFeedback;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class ReportController
{
    public function show(DiagnosticReport $report)
    {
        Gate::authorize('view', $report);

        return ApiResponse::success((new DiagnosticReportResource($report->load($this->relations())))->resolve(), 200, ['locale' => app()->getLocale()]);
    }

    public function feedback(Request $request, DiagnosticReport $report)
    {
        Gate::authorize('update', $report);
        $data = $request->validate(['helpful' => ['required', 'boolean'], 'comment' => ['nullable', 'string', 'max:2000'], 'confirmedMechanicDiagnosis' => ['nullable', 'string', 'max:2000']]);
        $feedback = ReportFeedback::query()->updateOrCreate(['diagnostic_report_id' => $report->id, 'user_id' => $request->user()->id], ['helpful' => $data['helpful'], 'correction_or_comment' => $data['comment'] ?? null, 'confirmed_mechanic_diagnosis' => $data['confirmedMechanicDiagnosis'] ?? null]);

        return ApiResponse::success(['id' => (string) $feedback->id, 'helpful' => (bool) $feedback->helpful], $feedback->wasRecentlyCreated ? 201 : 200);
    }

    public function refreshEstimate(Request $request, DiagnosticReport $report)
    {
        Gate::authorize('update', $report);
        $key = trim((string) $request->header('Idempotency-Key'));
        $key = $key === '' ? null : mb_substr($key, 0, 128);
        $report->loadMissing('session');
        if ($key && $existing = $report->priceSearches()->where('idempotency_key', $key)->first()) {
            return ApiResponse::success(['reportId' => (string) $report->id, 'priceSearchId' => (string) $existing->id, 'status' => $existing->status, 'estimateStatus' => $report->estimate?->status], in_array($existing->status, ['queued', 'running'], true) ? 202 : 200);
        }
        $search = $report->priceSearches()->create([
            'country_code' => $report->session->market_country_code ?? 'US', 'city' => $report->session->market_city,
            'currency' => $report->session->market_currency ?? 'USD', 'query_json' => ['refresh' => true],
            'status' => 'queued', 'idempotency_key' => $key,
        ]);
        RefreshServiceEstimate::dispatch($report->id, $search->id)->afterCommit();

        return ApiResponse::success(['reportId' => (string) $report->id, 'priceSearchId' => (string) $search->id, 'status' => 'queued'], 202);
    }

    public function share(DiagnosticReport $report)
    {
        Gate::authorize('view', $report);
        $expires = now()->addMinutes(30);

        return ApiResponse::success(['url' => URL::temporarySignedRoute('reports.shared', $expires, ['report' => $report->id, 'locale' => app()->getLocale()]), 'expiresAt' => $expires->utc()->toIso8601ZuluString()]);
    }

    public function shared(Request $request, DiagnosticReport $report)
    {
        if ($request->query('locale') && in_array($request->query('locale'), ['en', 'ar'], true)) {
            app()->setLocale($request->query('locale'));
        }

        $payload = (new DiagnosticReportResource($report->load($this->relations())))->resolve($request);

        if (! str_contains(strtolower((string) $request->header('Accept')), 'text/html')) {
            return ApiResponse::success($payload, 200, ['locale' => app()->getLocale()]);
        }

        return response()
            ->view('public.shared-report', ['report' => $payload, 'locale' => app()->getLocale()])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'private, no-store');
    }

    private function relations(): array
    {
        return ['vehicle', 'translations', 'faults.translations', 'faults.causes.translations', 'faults.actions.translations', 'faults.parts.translations', 'faults.evidence', 'actions.translations', 'evidence', 'estimate.lineItems', 'priceSearches.sources'];
    }
}
