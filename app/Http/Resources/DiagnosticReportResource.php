<?php

namespace App\Http\Resources;

use App\Models\DiagnosticReport;
use App\Support\ContentLocale;
use App\Support\VehicleDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiagnosticReport */
class DiagnosticReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app(ContentLocale::class)->forReport($this->resource, $request->attributes->get('reportLocaleOverride'));
        $tr = $this->translations->firstWhere('locale', $locale);
        $translate = static fn ($items, string $field = 'text') => $items->map(function ($item) use ($locale, $field) {
            $t = $item->translations->firstWhere('locale', $locale);

            return $t?->{$field};
        })->filter()->values()->all();

        $faults = $this->faults->map(function ($fault) use ($locale, $translate) {
            $t = $fault->translations->firstWhere('locale', $locale);

            return [
                'code' => $fault->canonical_fault_code, 'obdCode' => $fault->obd_code, 'title' => $t?->title, 'description' => $t?->description,
                'confidence' => (float) $fault->confidence, 'severity' => $fault->severity,
                'possibleCauses' => $translate($fault->causes),
                'recommendedActions' => $fault->actions->map(fn ($a) => ($a->translations->firstWhere('locale', $locale))?->text)->filter()->values()->all(),
                'recommendedParts' => $fault->parts->map(function ($p) use ($locale) {
                    $t = $p->translations->firstWhere('locale', $locale);

                    return ['canonicalName' => $p->canonical_part_name, 'name' => $t?->display_name, 'reason' => $t?->reason, 'partNumber' => $p->part_number, 'required' => (bool) $p->required, 'compatibilityConfidence' => (float) $p->compatibility_confidence];
                })->all(),
                'evidence' => $fault->evidence->map(fn ($e) => ['sourceType' => $e->source_type, 'referenceId' => $e->source_record_id, 'observation' => $locale === 'ar' ? $e->observation_ar : $e->observation_en, 'reliability' => (float) $e->reliability])->all(),
            ];
        })->all();

        $safeChecks = $this->actions->where('action_type', 'safe_check')->map(function ($a) use ($locale) {
            $t = $a->translations->firstWhere('locale', $locale);

            return ['text' => $t?->text, 'stopCondition' => $t?->stop_condition_text];
        })->all();
        $actions = $this->actions->where('action_type', 'recommended_action')->map(function ($a) use ($locale) {
            $t = $a->translations->firstWhere('locale', $locale);

            return ['id' => (string) $a->id, 'code' => $a->canonical_code, 'text' => $t?->text, 'priority' => (int) $a->priority, 'professionalRequired' => (bool) $a->professional_required];
        })->all();
        $estimate = $this->estimate;
        $latestPriceSearch = $this->priceSearches->sortByDesc('created_at')->first();
        $sources = $this->priceSearches->whereIn('status', ['available', 'partial'])->sortByDesc('searched_at')->take(1)->flatMap->sources->sortByDesc('retrieved_at')->unique('url')->map(fn ($s) => ['id' => (string) $s->id, 'url' => $s->url, 'title' => $s->title, 'domain' => $s->domain, 'retrievedAt' => $s->retrieved_at?->utc()->toIso8601ZuluString(), 'sourceDate' => $s->source_date?->utc()->toIso8601ZuluString(), 'qualityScore' => $s->quality_score !== null ? (float) $s->quality_score : null])->values()->all();

        return [
            'reportLocale' => $locale,
            'id' => (string) $this->id, 'sessionId' => (string) $this->diagnostic_session_id, 'vehicleId' => (string) $this->vehicle_id,
            'vehicleName' => app(VehicleDisplayName::class)->name($this->vehicle, $locale), 'title' => $tr?->title, 'summary' => $tr?->summary,
            'confidence' => (float) $this->overall_confidence, 'severity' => $this->severity, 'drivingRecommendation' => $this->driving_recommendation,
            'drivingAdvice' => $tr?->driving_advice, 'evidenceQuality' => $this->evidence_quality, 'professionalInspectionRequired' => (bool) $this->professional_inspection_required,
            'suspectedFaults' => $faults, 'safeChecks' => array_values($safeChecks), 'recommendedActions' => array_values($actions),
            'limitations' => $this->localizedArray($this->limitations, $locale), 'missingEvidence' => $this->missing_evidence ?? [],
            'missingEvidenceLabels' => collect($this->missing_evidence ?? [])->map(fn ($code) => trans("reports.missing_evidence.$code", [], $locale))->all(),
            'estimateStatus' => $estimate ? $estimate->status : $latestPriceSearch?->status,
            'serviceEstimate' => $estimate ? ['status' => $estimate->status, 'currency' => $estimate->currency, 'low' => $estimate->total_low, 'typical' => $estimate->total_typical, 'high' => $estimate->total_high, 'confidence' => $estimate->confidence, 'searchedAt' => $estimate->searched_at?->utc()->toIso8601ZuluString(), 'expiresAt' => $estimate->expires_at?->utc()->toIso8601ZuluString(), 'assumptions' => $this->localizedArray($estimate->assumptions_json, $locale), 'disclaimer' => config("automind.estimate_disclaimer.$locale"), 'lineItems' => $estimate->lineItems->map(fn ($item) => ['id' => (string) $item->id, 'category' => $item->category, 'canonicalCode' => $item->canonical_code, 'displayName' => $this->lineItemName($item, $locale), 'categoryLabel' => $this->label('category', $item->category, $locale, 'service'), 'unitLabel' => $this->label('unit', $item->unit, $locale, 'unit'), 'quantity' => $item->quantity, 'unit' => $item->unit, 'low' => $item->low_amount, 'typical' => $item->typical_amount, 'high' => $item->high_amount, 'currency' => $item->currency, 'sourceConfidence' => $item->source_confidence_metadata])->values()->all(), 'sources' => $sources] : null,
            'sources' => $sources, 'disclaimer' => $tr?->disclaimer, 'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private function lineItemName($item, string $locale): string
    {
        if ($item->category === 'part') {
            $part = $this->faults->flatMap->parts->firstWhere('canonical_part_name', $item->canonical_code);
            $name = $part?->translations->firstWhere('locale', $locale)?->display_name;
            if ($name) {
                return $name;
            }
        }
        $action = $this->actions->firstWhere('canonical_code', $item->canonical_code);

        return $action?->translations->firstWhere('locale', $locale)?->text
            ?: $this->label('category', $item->category, $locale, 'service');
    }

    private function label(string $group, ?string $code, string $locale, string $fallback): string
    {
        $key = "reports.$group.$code";

        return trans()->has($key, $locale, false) ? trans($key, [], $locale) : trans("reports.$group.$fallback", [], $locale);
    }

    private function localizedArray(?array $items, string $locale): array
    {
        return collect($items ?? [])->map(fn ($item) => is_array($item) ? ($item[$locale] ?? null) : $item)->filter()->values()->all();
    }
}
