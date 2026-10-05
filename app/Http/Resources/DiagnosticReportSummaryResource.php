<?php

namespace App\Http\Resources;

use App\Models\DiagnosticReport;
use App\Support\VehicleDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiagnosticReport */
class DiagnosticReportSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $translation = $this->translations->firstWhere('locale', $locale);

        return [
            'reportLocale' => $locale,
            'id' => (string) $this->id,
            'sessionId' => (string) $this->diagnostic_session_id,
            'vehicleId' => (string) $this->vehicle_id,
            'vehicleName' => app(VehicleDisplayName::class)->name($this->vehicle, $locale),
            'title' => $translation?->title,
            'summary' => $translation?->summary,
            'confidence' => (float) $this->overall_confidence,
            'severity' => $this->severity,
            'drivingRecommendation' => $this->driving_recommendation,
            'professionalInspectionRequired' => (bool) $this->professional_inspection_required,
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
