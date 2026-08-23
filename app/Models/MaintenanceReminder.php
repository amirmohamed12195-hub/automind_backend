<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceReminder extends UlidModel
{
    protected function casts(): array
    {
        return ['due_date' => 'date', 'snoozed_until' => 'datetime', 'last_notified_at' => 'datetime', 'notification_preferences' => 'array'];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<DiagnosticReport, $this> */
    public function sourceReport(): BelongsTo
    {
        return $this->belongsTo(DiagnosticReport::class, 'source_report_id');
    }

    /** @return BelongsTo<ReportAction, $this> */
    public function sourceAction(): BelongsTo
    {
        return $this->belongsTo(ReportAction::class, 'source_report_action_id');
    }
}
