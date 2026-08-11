<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingException;
use App\Models\DiagnosticSession;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\ReportEntitlementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiagnosticEntitlementController
{
    public function reserve(Request $request, DiagnosticSession $diagnosis, ReportEntitlementService $reports, EntitlementService $entitlements)
    {
        Gate::authorize('update', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'uploading', 'failed'], true)) {
            throw new BillingException('INVALID_DIAGNOSTIC_STATE', 'Entitlement can only be reserved before analysis.', 409);
        }
        $reservation = $reports->reserve($diagnosis);

        return ApiResponse::success(['reservationId' => $reservation?->id, 'status' => $reservation->status ?? 'billing_disabled', 'entitlements' => $entitlements->snapshot($request->user())]);
    }

    public function finalize(Request $request, DiagnosticSession $diagnosis, ReportEntitlementService $reports, EntitlementService $entitlements)
    {
        Gate::authorize('view', $diagnosis);
        if ($diagnosis->status !== 'completed' || ! $diagnosis->report()->exists()) {
            throw new BillingException('REPORT_NOT_READY', 'A report entitlement can only be finalized after the report is complete.', 409);
        }
        $reports->finalize($diagnosis);

        return ApiResponse::success($entitlements->snapshot($request->user()));
    }

    public function release(Request $request, DiagnosticSession $diagnosis, ReportEntitlementService $reports, EntitlementService $entitlements)
    {
        Gate::authorize('view', $diagnosis);
        if (! in_array($diagnosis->status, ['failed', 'cancelled'], true)) {
            throw new BillingException('INVALID_DIAGNOSTIC_STATE', 'Entitlement can only be released for a failed or canceled diagnosis.', 409);
        }
        $reports->release($diagnosis);

        return ApiResponse::success($entitlements->snapshot($request->user()));
    }
}
