<?php

namespace App\Http\Resources;

use App\Models\DiagnosticSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiagnosticSession */
class DiagnosticSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id, 'vehicleId' => (string) $this->vehicle_id, 'description' => $this->description,
            'selectedSymptoms' => $this->whenLoaded('symptoms', fn () => $this->symptoms->pluck('code')->values()->all(), []),
            'status' => $this->status, 'progress' => (int) $this->progress_percentage, 'currentStep' => $this->current_step,
            'inputLocale' => $this->input_locale, 'reportLocale' => $this->report_locale,
            'market' => ['countryCode' => $this->market_country_code, 'city' => $this->market_city, 'currency' => $this->market_currency],
            'clientReference' => $this->client_reference, 'error' => $this->error_code ? ['code' => $this->error_code, 'message' => $this->safe_error_message] : null,
            'mediaCount' => $this->whenCounted('media'), 'obdSnapshotCount' => $this->whenCounted('obdSnapshots'),
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(), 'updatedAt' => $this->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
