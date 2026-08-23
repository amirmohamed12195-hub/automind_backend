<?php

namespace App\Http\Resources;

use App\Models\MaintenanceReminder;
use App\Models\Vehicle;

final class MaintenanceReminderResource
{
    public static function make(MaintenanceReminder $reminder, Vehicle $vehicle): array
    {
        $due = $reminder->status === 'pending' && (
            ($reminder->due_date && $reminder->due_date->isPast())
            || ($reminder->due_km !== null && $reminder->due_km <= $vehicle->mileage_km)
        );
        $locale = app()->getLocale();

        return [
            'id' => (string) $reminder->id,
            'vehicleId' => (string) $reminder->vehicle_id,
            'serviceDefinitionId' => (string) $reminder->service_definition_id,
            'dueDate' => $reminder->due_date?->toDateString(),
            'dueKm' => $reminder->due_km,
            'status' => $reminder->status,
            'isDue' => $due,
            'snoozedUntil' => $reminder->snoozed_until?->utc()->toIso8601ZuluString(),
            'completedRecordId' => $reminder->completed_record_id,
            'notificationPreferences' => $reminder->notification_preferences,
            'sourceReportId' => $reminder->source_report_id,
            'sourceReportActionId' => $reminder->source_report_action_id,
            'sourceActionText' => $locale === 'ar'
                ? ($reminder->source_action_text_ar ?? $reminder->source_action_text_en)
                : $reminder->source_action_text_en,
        ];
    }
}
