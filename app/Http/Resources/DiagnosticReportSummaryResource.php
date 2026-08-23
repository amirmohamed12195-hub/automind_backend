<?php

namespace App\Http\Resources;

use App\Models\DiagnosticReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiagnosticReport */
class DiagnosticReportSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $translation = $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'en');

        return [
            'id' => (string) $this->id,
            'sessionId' => (string) $this->diagnostic_session_id,
            'vehicleId' => (string) $this->vehicle_id,
            'vehicleName' => trim($this->vehicle->brand.' '.$this->vehicle->model),
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
