<?php

namespace App\Jobs;

use App\Models\MaintenanceReminder;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMaintenanceReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('maintenance-reminders');
    }

    public function handle(UserNotificationService $notifications): void
    {
        MaintenanceReminder::query()->where('status', 'snoozed')->where('snoozed_until', '<=', now())->update(['status' => 'pending', 'snoozed_until' => null, 'updated_at' => now()]);
        MaintenanceReminder::query()->where('status', 'pending')->where(fn ($q) => $q->whereNull('last_notified_at')->orWhere('last_notified_at', '<=', now()->subDay()))->where(function ($q) {
            $q->whereDate('due_date', '<=', today())->orWhereHas('vehicle', fn ($v) => $v->whereColumn('vehicles.mileage_km', '>=', 'maintenance_reminders.due_km'));
        })->with('vehicle.user')->chunkById(200, function ($reminders) use ($notifications): void {
            foreach ($reminders as $reminder) {
                $vehicle = $reminder->vehicle;
                if ($vehicle->user !== null && $vehicle->user->maintenance_reminders_enabled) {
                    $notifications->send(
                        $vehicle->user,
                        'maintenance_due',
                        'Maintenance reminder',
                        'تذكير بالصيانة',
                        "Maintenance is due for {$vehicle->brand} {$vehicle->model}.",
                        "حان موعد صيانة {$vehicle->brand} {$vehicle->model}.",
                        ['vehicleId' => (string) $vehicle->id, 'reminderId' => (string) $reminder->id],
                    );
                }
                $reminder->update(['last_notified_at' => now()]);
            }
        });
    }
}
