<?php

use App\Jobs\PurgeExpiredData;
use App\Jobs\ReconcileUserBilling;
use App\Jobs\ReleaseStaleReportReservations;
use App\Jobs\SendMaintenanceReminders;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendMaintenanceReminders)->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::job(new PurgeExpiredData)->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::job(new ReconcileUserBilling)->hourly()->withoutOverlapping()->onOneServer();
Schedule::job(new ReleaseStaleReportReservations)->hourly()->withoutOverlapping()->onOneServer();
Schedule::call(function (): void {
    DeviceToken::query()
        ->where('enabled', true)
        ->where('last_seen_at', '<', now()->subDays(max(1, (int) config('services.fcm.stale_token_days', 90))))
        ->update(['enabled' => false, 'updated_at' => now()]);
})->name('prune-stale-device-tokens')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
