<?php

namespace App\Jobs;

use App\Models\DiagnosticSession;
use App\Models\ReportEntitlementReservation;
use App\Services\Billing\ReportEntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ReleaseStaleReportReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('billing');
    }

    public function handle(ReportEntitlementService $reports): void
    {
        $cutoff = now()->subHours(max(1, (int) config('billing.reconciliation.stale_reservation_hours', 2)));
        ReportEntitlementReservation::query()
            ->where('status', 'reserved')
            ->where('reserved_at', '<', $cutoff)
            ->whereHas('diagnosis', fn ($query) => $query->whereIn('status', ['failed', 'cancelled'])->orWhere(function ($stale) use ($cutoff): void {
                $stale->whereIn('status', ['queued', 'analyzing'])->where('updated_at', '<', $cutoff);
            }))
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use ($reports): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, $reports): void {
                        DiagnosticSession::query()->whereKey($reservation->diagnostic_session_id)
                            ->whereIn('status', ['queued', 'analyzing'])
                            ->update(['status' => 'failed', 'error_code' => 'analysis_stale', 'safe_error_message' => 'Analysis did not complete and may be retried.', 'failed_at' => now(), 'updated_at' => now()]);
                        $reports->release($reservation->diagnostic_session_id);
                    });
                }
            });
    }
}
