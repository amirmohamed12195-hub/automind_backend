<?php

namespace App\Jobs;

use App\Models\DiagnosticReport;
use App\Models\PartRecommendation;
use App\Models\PartRecommendationTranslation;
use App\Models\PriceSearch;
use App\Services\Pricing\PriceResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RefreshServiceEstimate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $reportId, public readonly ?string $priceSearchId = null)
    {
        $this->onQueue('price-search');
    }

    public function backoff(): array
    {
        return [random_int(50, 70), random_int(270, 330)];
    }

    public function handle(PriceResearchService $research): void
    {
        Cache::lock("estimate:{$this->reportId}", 180)->block(5, function () use ($research): void {
            $report = DiagnosticReport::query()->with(['session', 'vehicle', 'faults.parts.translations'])->findOrFail($this->reportId);
            $structured = ['suspectedFaults' => $report->faults->map(fn ($fault) => ['recommendedParts' => $fault->parts->map(function ($part) {
                $en = $this->translation($part, 'en');
                $ar = $this->translation($part, 'ar');
                $enName = $en === null ? $part->canonical_part_name : $en->display_name;
                $arName = $ar === null ? $part->canonical_part_name : $ar->display_name;
                $enReason = $en === null ? '' : $en->reason;
                $arReason = $ar === null ? '' : $ar->reason;

                return ['canonicalName' => $part->canonical_part_name, 'name' => ['en' => $enName, 'ar' => $arName], 'reason' => ['en' => $enReason, 'ar' => $arReason], 'partNumber' => $part->part_number, 'required' => (bool) $part->required, 'compatibilityConfidence' => (float) $part->compatibility_confidence, 'searchKeywords' => ['en' => $part->vehicle_compatibility_text ?? $part->canonical_part_name, 'ar' => $arName]];
            })->all()])->all()];
            $safetyId = hash_hmac('sha256', (string) $report->user_id, (string) config('app.key'));
            $search = $this->priceSearchId ? PriceSearch::query()->where('diagnostic_report_id', $report->id)->findOrFail($this->priceSearchId) : null;
            $research->research($report, $structured, $safetyId, $search);
        });
    }

    private function translation(PartRecommendation $part, string $locale): ?PartRecommendationTranslation
    {
        foreach ($part->translations as $translation) {
            if ($translation->locale === $locale) {
                return $translation;
            }
        }

        return null;
    }
}
