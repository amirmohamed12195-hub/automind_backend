<?php

namespace App\Services\Pricing;

use App\Contracts\CurrencyRateProvider;
use App\Contracts\WebPriceSearchProvider;
use App\DTO\AiProviderResult;
use App\Exceptions\AiProviderException;
use App\Models\DiagnosticReport;
use App\Models\LaborRateSource;
use App\Models\PartRecommendation;
use App\Models\PriceSearch;
use App\Models\ServiceEstimate;
use App\Models\WebSource;
use App\Services\Ai\AiRunRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PriceResearchService
{
    public function __construct(
        private WebPriceSearchProvider $provider,
        private AiRunRecorder $runs,
        private ServiceEstimateCalculator $calculator,
        private CurrencyRateProvider $currencies,
    ) {}

    public function research(DiagnosticReport $report, array $structuredReport, string $safetyIdentifier, ?PriceSearch $search = null): ?ServiceEstimate
    {
        $parts = collect($structuredReport['suspectedFaults'])->flatMap(fn ($fault) => $fault['recommendedParts'])->values()->all();
        if ($parts === []) {
            return null;
        }

        $report->loadMissing(['session', 'vehicle', 'faults.parts']);
        $market = [
            'countryCode' => $report->session->market_country_code,
            'city' => $report->session->market_city,
            'currency' => strtoupper($report->session->market_currency ?? 'USD'),
        ];
        $forceRefresh = (bool) data_get($search?->query_json, 'refresh', false) || $search?->status === 'queued';
        $search ??= $report->priceSearches()->create([
            'country_code' => $market['countryCode'] ?? 'US', 'city' => $market['city'], 'currency' => $market['currency'],
            'query_json' => ['parts' => $parts], 'status' => 'running',
        ]);
        $search->update(['query_json' => ['parts' => $parts, 'refresh' => $forceRefresh], 'status' => 'running']);

        $vehicle = $structuredReport['vehicle'] ?? [
            'brand' => $report->vehicle->brand, 'model' => $report->vehicle->model, 'year' => $report->vehicle->year,
            'engine' => $report->vehicle->engine, 'fuelType' => $report->vehicle->fuel_type,
            'transmission' => $report->vehicle->transmission,
        ];

        try {
            $cacheKey = $this->cacheKey($vehicle, $parts, $market);
            $cached = $forceRefresh ? null : Cache::get($cacheKey);
            if ($cached instanceof AiProviderResult) {
                $result = $cached;
            } else {
                $result = $this->runs->record(
                    $report->session,
                    'price_research',
                    1,
                    fn () => $this->provider->research($vehicle, $parts, $market, $safetyIdentifier),
                );
                Cache::put($cacheKey, $result, now()->addDays(config('automind.price_search_ttl_days')));
            }
        } catch (Throwable $e) {
            $search->update(['status' => 'failed', 'searched_at' => now(), 'expires_at' => now()->addDay()]);
            if ($forceRefresh && $e instanceof AiProviderException && $e->transient) {
                throw $e;
            }
            if ($existing = $report->estimate()->first()) {
                return $existing;
            }

            return $report->estimate()->create($this->unavailableEstimate($market, 'Current sourced prices were unavailable.', 'تعذر الحصول على أسعار حالية موثقة.'));
        }

        return DB::transaction(fn () => $this->persist($report, $search, $result, $market));
    }

    private function persist(DiagnosticReport $report, PriceSearch $search, AiProviderResult $result, array $market): ServiceEstimate
    {
        /** @var array<string, WebSource> $sourceByUrl */
        $sourceByUrl = [];
        foreach ($result->metadata['sources'] ?? [] as $sourceData) {
            $url = is_array($sourceData) ? (string) ($sourceData['url'] ?? '') : '';
            $host = $this->safeSourceHost($url);
            if ($host === null) {
                continue;
            }
            $source = WebSource::query()->firstOrCreate(
                ['price_search_id' => $search->id, 'url_hash' => hash('sha256', $url)],
                ['url' => $url, 'title' => (string) ($sourceData['title'] ?? $host), 'domain' => $host, 'source_type' => 'merchant', 'retrieved_at' => now(), 'quality_score' => '0.5000', 'citation_metadata_json' => $sourceData],
            );
            $sourceByUrl[$url] = $source;
        }

        /** @var array<string, array<string, list<string>>> $amountsByPartAndCondition */
        $amountsByPartAndCondition = [];
        /** @var array<string, array<string, array<string, true>>> $domainsByPartAndCondition */
        $domainsByPartAndCondition = [];
        /** @var array<string, PartRecommendation> $partsById */
        $partsById = [];
        $usedSourceIds = [];
        foreach ($result->data['quotes'] ?? [] as $quote) {
            if (! is_array($quote)) {
                continue;
            }
            /** @var PartRecommendation|null $part */
            $part = $report->faults->flatMap->parts->firstWhere('canonical_part_name', $quote['canonicalPartName'] ?? null);
            $source = $sourceByUrl[(string) ($quote['sourceUrl'] ?? '')] ?? null;
            $amount = $this->decimal($quote['amount'] ?? null);
            $shipping = $this->nullableDecimal($quote['shippingAmount'] ?? null);
            $condition = (string) ($quote['condition'] ?? 'unknown');
            $observedAt = $this->currentSourceDate($quote['observedAt'] ?? null);
            if (! $part || ! $source || $amount === null || $observedAt === false || trim((string) ($quote['compatibilityEvidence'] ?? '')) === '' || ! in_array($condition, ['new', 'used', 'remanufactured', 'unknown'], true)) {
                continue;
            }

            $currency = strtoupper((string) ($quote['currency'] ?? ''));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                continue;
            }
            $conversion = $this->conversion($amount, $shipping, $currency, $market['currency']);
            $part->quotes()->create([
                'web_source_id' => $source->id, 'merchant' => (string) ($quote['merchant'] ?? $source->domain),
                'condition' => $condition, 'brand_or_manufacturer' => $quote['brandOrManufacturer'] ?? null,
                'part_number' => $quote['partNumber'] ?? null, 'amount' => $amount, 'currency' => $currency,
                'normalized_amount' => $conversion['amount'] ?? null, 'normalized_currency' => isset($conversion['amount']) ? $market['currency'] : null,
                'normalized_shipping_amount' => $conversion['shipping'] ?? null,
                'currency_rate' => $conversion['rate'] ?? null, 'currency_rate_provider' => $conversion['provider'] ?? null,
                'currency_rate_effective_at' => $conversion['effectiveAt'] ?? null,
                'availability' => $quote['availability'] ?? null, 'shipping_amount' => $shipping,
                'tax_included' => $quote['taxIncluded'] ?? null, 'observed_at' => $observedAt ?: now(),
            ]);
            $usedSourceIds[] = (string) $source->id;
            $source->update([
                'title' => (string) ($quote['sourceTitle'] ?? $source->title), 'source_date' => $observedAt ?: null,
                'raw_price_text' => $quote['rawPriceText'] ?? null,
                'citation_metadata_json' => [...($source->citation_metadata_json ?? []), 'compatibilityEvidence' => $quote['compatibilityEvidence']],
            ]);

            if ($conversion === null) {
                continue;
            }
            $partId = (string) $part->id;
            $partsById[$partId] = $part;
            $amountsByPartAndCondition[$partId][$condition][] = bcadd($conversion['amount'], $conversion['shipping'] ?? '0.00', 2);
            $domainsByPartAndCondition[$partId][$condition][$source->domain] = true;
        }
        $unusedSources = WebSource::query()->where('price_search_id', $search->id);
        if ($usedSourceIds !== []) {
            $unusedSources->whereNotIn('id', array_unique($usedSourceIds));
        }
        $unusedSources->delete();

        $partLineItems = [];
        $selectedDomainCount = 0;
        foreach ($amountsByPartAndCondition as $partId => $conditionGroups) {
            $condition = $this->preferredCondition($conditionGroups);
            $amounts = $conditionGroups[$condition];
            usort($amounts, fn (string $left, string $right) => bccomp($left, $right, 2));
            $selectedDomainCount += count($domainsByPartAndCondition[$partId][$condition] ?? []);
            $partLineItems[] = [
                'category' => 'part', 'canonicalCode' => $partsById[$partId]->canonical_part_name,
                'quantity' => '1.000', 'unit' => 'each', 'currency' => $market['currency'],
                'low' => $amounts[0], 'typical' => $amounts[(int) floor((count($amounts) - 1) / 2)], 'high' => $amounts[count($amounts) - 1],
                'condition' => $condition, 'sourceCount' => count($domainsByPartAndCondition[$partId][$condition] ?? []),
                'metadata' => ['condition' => $condition, 'independentSourceDomains' => count($domainsByPartAndCondition[$partId][$condition] ?? [])],
            ];
        }

        $searchedAt = now();
        $expiresAt = now()->addDays(config('automind.price_search_ttl_days'));
        $requiredPartCount = $report->faults->flatMap->parts->unique('canonical_part_name')->count();
        $searchStatus = $partLineItems === [] ? 'unavailable' : (count($partLineItems) < $requiredPartCount || ($result->data['status'] ?? null) !== 'available' ? 'partial' : 'available');
        $search->update(['status' => $searchStatus, 'searched_at' => $searchedAt, 'expires_at' => $expiresAt]);

        if ($partLineItems === []) {
            if ($existing = $report->estimate()->first()) {
                return $existing;
            }

            return $report->estimate()->create($this->unavailableEstimate($market, 'Compatible attributable current prices were not available.', 'لم تتوفر أسعار حالية موثقة لقطع متوافقة.'));
        }

        $labor = $this->laborLineItems($partLineItems, $market);
        $lineItems = [...$partLineItems, ...$labor['items']];
        $partTotals = $this->calculator->calculate($partLineItems);
        $laborTotals = $labor['items'] === [] ? null : $this->calculator->calculate($labor['items']);
        $totals = $this->calculator->calculate($lineItems);
        $conditionSummary = collect($partLineItems)->map(fn (array $item) => [
            'en' => "{$item['canonicalCode']} uses {$item['condition']}-condition prices from {$item['sourceCount']} independent source domain(s).",
            'ar' => "يعتمد {$item['canonicalCode']} على أسعار حالة {$item['condition']} من {$item['sourceCount']} نطاق مصادر مستقلة.",
        ])->all();
        $basisAssumption = $labor['complete']
            ? ['en' => 'Labor uses current administrator or sourced hours and hourly-rate ranges. Taxes, fees, and towing are excluded unless separately configured.', 'ar' => 'تستخدم العمالة نطاقات حالية معتمدة أو موثقة لساعات العمل وأسعار الساعة. ولا تشمل الضرائب أو الرسوم أو القطر ما لم تُضبط بشكل منفصل.']
            : ['en' => 'Totals include sourced compatible parts only; labor, taxes, fees, and towing are excluded because no complete current configured basis was available.', 'ar' => 'تشمل الإجماليات أسعار القطع المتوافقة الموثقة فقط؛ ولا تشمل العمالة أو الضرائب أو الرسوم أو القطر لعدم توافر أساس حالي مكتمل ومُعدّ لها.'];
        $estimateStatus = $searchStatus === 'available' && $labor['complete'] ? 'available' : 'partial';
        $estimate = $report->estimate()->updateOrCreate([], [
            'status' => $estimateStatus, 'country_code' => $market['countryCode'], 'city' => $market['city'], 'currency' => $market['currency'],
            'parts_low' => $partTotals['low'], 'parts_typical' => $partTotals['typical'], 'parts_high' => $partTotals['high'],
            'labor_low' => $laborTotals['low'] ?? null, 'labor_typical' => $laborTotals['typical'] ?? null, 'labor_high' => $laborTotals['high'] ?? null, 'fees_low' => null, 'fees_typical' => null, 'fees_high' => null,
            'total_low' => $totals['low'], 'total_typical' => $totals['typical'], 'total_high' => $totals['high'],
            'confidence' => $selectedDomainCount >= count($partLineItems) * 2 ? ($labor['complete'] ? '0.6500' : '0.5500') : ($labor['complete'] ? '0.4500' : '0.3500'),
            'assumptions_json' => [...$conditionSummary, $basisAssumption],
            'searched_at' => $searchedAt, 'expires_at' => $expiresAt,
        ]);
        $estimate->lineItems()->delete();
        foreach ($lineItems as $item) {
            $estimate->lineItems()->create([
                'category' => $item['category'], 'canonical_code' => $item['canonicalCode'], 'quantity' => $item['quantity'], 'unit' => $item['unit'],
                'low_amount' => $item['low'], 'typical_amount' => $item['typical'], 'high_amount' => $item['high'], 'currency' => $item['currency'],
                'source_confidence_metadata' => $item['metadata'],
            ]);
        }

        return $estimate->fresh('lineItems');
    }

    private function cacheKey(array $vehicle, array $parts, array $market): string
    {
        $normalizedParts = collect($parts)->sortBy('canonicalName')->values()->all();
        $payload = ['model' => config('openai.price_search_model'), 'vehicle' => $vehicle, 'parts' => $normalizedParts, 'market' => $market];

        return 'price-research:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function safeSourceHost(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return $host;
    }

    private function currentSourceDate(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return false;
        }

        return $date->lt(now()->subDays(config('automind.price_source_max_age_days'))) || $date->isFuture() ? false : $date;
    }

    private function preferredCondition(array $groups): string
    {
        foreach (['new', 'remanufactured', 'used', 'unknown'] as $condition) {
            if (($groups[$condition] ?? []) !== []) {
                return $condition;
            }
        }

        return 'unknown';
    }

    /** @return array{amount: string, shipping: ?string, rate: string, provider: string, effectiveAt: string}|null */
    private function conversion(string $amount, ?string $shipping, string $baseCurrency, string $quoteCurrency): ?array
    {
        try {
            $conversion = $this->currencies->conversion($baseCurrency, $quoteCurrency);
        } catch (RuntimeException) {
            return null;
        }
        $rate = (string) ($conversion['rate'] ?? '');
        if (preg_match('/^\d+(?:\.\d+)?$/', $rate) !== 1 || bccomp($rate, '0', 10) <= 0) {
            return null;
        }

        return [
            'amount' => bcmul($amount, $rate, 2),
            'shipping' => $shipping === null ? null : bcmul($shipping, $rate, 2),
            'rate' => $rate,
            'provider' => (string) ($conversion['provider'] ?? 'unknown'),
            'effectiveAt' => (string) ($conversion['effectiveAt'] ?? now()->toIso8601String()),
        ];
    }

    /** @param list<array<string, mixed>> $partLineItems
     * @return array{items: list<array<string, mixed>>, complete: bool}
     */
    private function laborLineItems(array $partLineItems, array $market): array
    {
        $items = [];
        $covered = 0;
        foreach ($partLineItems as $partItem) {
            $source = $this->laborSource((string) $partItem['canonicalCode'], $market);
            if ($source === null) {
                continue;
            }
            $item = $this->laborLineItem($source, (string) $partItem['canonicalCode'], $market);
            if ($item !== null) {
                $items[] = $item;
                $covered++;
            }
        }
        if ($covered === count($partLineItems)) {
            return ['items' => $items, 'complete' => true];
        }

        $default = $this->laborSource('default', $market);
        $defaultItem = $default ? $this->laborLineItem($default, 'general_service_labor', $market) : null;
        if ($defaultItem !== null) {
            return ['items' => [$defaultItem], 'complete' => true];
        }

        return ['items' => $items, 'complete' => false];
    }

    private function laborSource(string $category, array $market): ?LaborRateSource
    {
        $city = trim((string) ($market['city'] ?? ''));
        $sources = LaborRateSource::query()
            ->where('country_code', strtoupper((string) $market['countryCode']))
            ->where('service_category', $category)
            ->where('observed_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($city !== '', fn ($query) => $query->where(fn ($location) => $location->whereNull('city')->orWhere('city', $city)), fn ($query) => $query->whereNull('city'))
            ->latest('observed_at')->get();

        return $sources->sortByDesc(fn (LaborRateSource $source) => ($city !== '' && mb_strtolower((string) $source->city) === mb_strtolower($city) ? 1 : 0))->first();
    }

    /** @return array<string, mixed>|null */
    private function laborLineItem(LaborRateSource $source, string $code, array $market): ?array
    {
        $hours = [(string) $source->hours_low, (string) $source->hours_typical, (string) $source->hours_high];
        $rates = [(string) $source->hourly_low, (string) $source->hourly_typical, (string) $source->hourly_high];
        if (in_array('', $hours, true) || bccomp($hours[0], '0', 2) < 0 || bccomp($hours[0], $hours[1], 2) > 0 || bccomp($hours[1], $hours[2], 2) > 0 || bccomp($rates[0], '0', 2) < 0 || bccomp($rates[0], $rates[1], 2) > 0 || bccomp($rates[1], $rates[2], 2) > 0) {
            return null;
        }
        $conversion = $this->conversion('1.00', null, $source->currency, (string) $market['currency']);
        if ($conversion === null) {
            return null;
        }
        $rate = $conversion['rate'];
        $normalizedRates = array_map(fn (string $value) => bcmul($value, $rate, 2), $rates);

        return [
            'category' => 'labor', 'canonicalCode' => $code, 'quantity' => '1.000', 'unit' => 'job', 'currency' => $market['currency'],
            'low' => bcmul($hours[0], $normalizedRates[0], 2), 'typical' => bcmul($hours[1], $normalizedRates[1], 2), 'high' => bcmul($hours[2], $normalizedRates[2], 2),
            'metadata' => [
                'laborRateSourceId' => (string) $source->id, 'serviceCategory' => $source->service_category,
                'hours' => ['low' => $hours[0], 'typical' => $hours[1], 'high' => $hours[2]],
                'hourlyRate' => ['low' => $normalizedRates[0], 'typical' => $normalizedRates[1], 'high' => $normalizedRates[2]],
                'currencyRate' => $conversion['rate'], 'currencyRateProvider' => $conversion['provider'], 'currencyRateEffectiveAt' => $conversion['effectiveAt'],
                'observedAt' => $source->observed_at?->utc()->toIso8601String(), 'expiresAt' => $source->expires_at?->utc()->toIso8601String(),
            ],
        ];
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : $this->decimal($value);
    }

    private function decimal(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

        return bcadd($value, '0', 2);
    }

    private function unavailableEstimate(array $market, string $english, string $arabic): array
    {
        return [
            'status' => 'unavailable', 'country_code' => $market['countryCode'], 'city' => $market['city'], 'currency' => $market['currency'],
            'assumptions_json' => [['en' => $english, 'ar' => $arabic]], 'searched_at' => now(), 'expires_at' => now()->addDay(),
        ];
    }
}
