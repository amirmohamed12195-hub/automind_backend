<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id, 'mechanicId' => (string) $this->mechanic_id, 'vehicleId' => (string) $this->vehicle_id,
            'reportId' => $this->diagnostic_report_id ? (string) $this->diagnostic_report_id : null, 'status' => $this->status,
            'requestedStart' => $this->requested_start_at?->utc()->toIso8601ZuluString(), 'requestedEnd' => $this->requested_end_at?->utc()->toIso8601ZuluString(),
            'customerNote' => $this->customer_note, 'mechanicNote' => $this->mechanic_note, 'cancellationReason' => $this->cancellation_reason,
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
