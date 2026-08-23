<?php

namespace App\Http\Resources;

use App\Models\DiagnosticReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiagnosticReport */
class DiagnosticReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $tr = $this->translations->firstWhere('locale', $locale) ?? $this->translations->firstWhere('locale', 'en');
        $translate = static fn ($items, string $field = 'text') => $items->map(function ($item) use ($locale, $field) {
            $t = $item->translations->firstWhere('locale', $locale) ?? $item->translations->firstWhere('locale', 'en');

            return $t?->{$field};
        })->filter()->values()->all();

        $faults = $this->faults->map(function ($fault) use ($locale, $translate) {
            $t = $fault->translations->firstWhere('locale', $locale) ?? $fault->translations->firstWhere('locale', 'en');

            return [
                'code' => $fault->canonical_fault_code, 'obdCode' => $fault->obd_code, 'title' => $t?->title, 'description' => $t?->description,
                'confidence' => (float) $fault->confidence, 'severity' => $fault->severity,
                'possibleCauses' => $translate($fault->causes),
                'recommendedActions' => $fault->actions->map(fn ($a) => ($a->translations->firstWhere('locale', $locale) ?? $a->translations->firstWhere('locale', 'en'))?->text)->filter()->values()->all(),
                'recommendedParts' => $fault->parts->map(function ($p) use ($locale) {
                    $t = $p->translations->firstWhere('locale', $locale) ?? $p->translations->firstWhere('locale', 'en');

                    return ['canonicalName' => $p->canonical_part_name, 'name' => $t?->display_name, 'reason' => $t?->reason, 'partNumber' => $p->part_number, 'required' => (bool) $p->required, 'compatibilityConfidence' => (float) $p->compatibility_confidence];
                })->all(),
                'evidence' => $fault->evidence->map(fn ($e) => ['sourceType' => $e->source_type, 'referenceId' => $e->source_record_id, 'observation' => $locale === 'ar' ? $e->observation_ar : $e->observation_en, 'reliability' => (float) $e->reliability])->all(),
            ];
        })->all();

        $safeChecks = $this->actions->where('action_type', 'safe_check')->map(function ($a) use ($locale) {
            $t = $a->translations->firstWhere('locale', $locale) ?? $a->translations->firstWhere('locale', 'en');

            return ['text' => $t?->text, 'stopCondition' => $t?->stop_condition_text];
        })->all();
        $actions = $this->actions->where('action_type', 'recommended_action')->map(function ($a) use ($locale) {
            $t = $a->translations->firstWhere('locale', $locale) ?? $a->translations->firstWhere('locale', 'en');

            return ['id' => (string) $a->id, 'code' => $a->canonical_code, 'text' => $t?->text, 'priority' => (int) $a->priority, 'professionalRequired' => (bool) $a->professional_required];
        })->all();
        $estimate = $this->estimate;
        $sources = $this->priceSearches->whereIn('status', ['available', 'partial'])->sortByDesc('searched_at')->take(1)->flatMap->sources->sortByDesc('retrieved_at')->unique('url')->map(fn ($s) => ['id' => (string) $s->id, 'url' => $s->url, 'title' => $s->title, 'domain' => $s->domain, 'retrievedAt' => $s->retrieved_at?->utc()->toIso8601ZuluString(), 'sourceDate' => $s->source_date?->utc()->toIso8601ZuluString(), 'qualityScore' => $s->quality_score !== null ? (float) $s->quality_score : null])->values()->all();

        return [
            'id' => (string) $this->id, 'sessionId' => (string) $this->diagnostic_session_id, 'vehicleId' => (string) $this->vehicle_id,
            'vehicleName' => trim($this->vehicle->brand.' '.$this->vehicle->model), 'title' => $tr?->title, 'summary' => $tr?->summary,
            'confidence' => (float) $this->overall_confidence, 'severity' => $this->severity, 'drivingRecommendation' => $this->driving_recommendation,
            'drivingAdvice' => $tr?->driving_advice, 'evidenceQuality' => $this->evidence_quality, 'professionalInspectionRequired' => (bool) $this->professional_inspection_required,
            'suspectedFaults' => $faults, 'safeChecks' => array_values($safeChecks), 'recommendedActions' => array_values($actions),
            'limitations' => $this->localizedArray($this->limitations, $locale), 'missingEvidence' => $this->missing_evidence ?? [],
            'serviceEstimate' => $estimate ? ['status' => $estimate->status, 'currency' => $estimate->currency, 'low' => $estimate->total_low, 'typical' => $estimate->total_typical, 'high' => $estimate->total_high, 'confidence' => $estimate->confidence, 'searchedAt' => $estimate->searched_at?->utc()->toIso8601ZuluString(), 'expiresAt' => $estimate->expires_at?->utc()->toIso8601ZuluString(), 'assumptions' => $this->localizedArray($estimate->assumptions_json, $locale), 'disclaimer' => config("automind.estimate_disclaimer.$locale"), 'lineItems' => $estimate->lineItems->map(fn ($item) => ['id' => (string) $item->id, 'category' => $item->category, 'canonicalCode' => $item->canonical_code, 'quantity' => $item->quantity, 'unit' => $item->unit, 'low' => $item->low_amount, 'typical' => $item->typical_amount, 'high' => $item->high_amount, 'currency' => $item->currency, 'sourceConfidence' => $item->source_confidence_metadata])->values()->all(), 'sources' => $sources] : null,
            'sources' => $sources, 'disclaimer' => $tr?->disclaimer, 'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private function localizedArray(?array $items, string $locale): array
    {
        return collect($items ?? [])->map(fn ($item) => is_array($item) ? ($item[$locale] ?? $item['en'] ?? null) : $item)->filter()->values()->all();
    }
}
