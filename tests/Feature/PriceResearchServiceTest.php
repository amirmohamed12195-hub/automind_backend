<?php

namespace Tests\Feature;

use App\Contracts\WebPriceSearchProvider;
use App\DTO\AiProviderResult;
use App\Exceptions\AiProviderException;
use App\Models\AiRun;
use App\Models\CurrencyRate;
use App\Models\DiagnosticReport;
use App\Models\DiagnosticSession;
use App\Models\LaborRateSource;
use App\Models\PartPriceQuote;
use App\Models\PriceSearch;
use App\Models\Vehicle;
use App\Models\WebSource;
use App\Services\Diagnostics\DiagnosticReportPersister;
use App\Services\Pricing\PriceResearchService;
use RuntimeException;
use Tests\Fakes\FakeAiProviders;

class PriceResearchServiceTest extends ApiTestCase
{
    public function test_quotes_are_filtered_grouped_by_part_and_condition_and_calculated_with_decimals(): void
    {
        [$report, $structured] = $this->reportWithParts();
        $sourceA = 'https://parts-a.example/coil';
        $sourceB = 'https://parts-b.example/coil';
        $sourceStale = 'https://parts-c.example/coil';
        $provider = new FixturePriceSearchProvider(new AiProviderResult(
            ['status' => 'available', 'reason' => null, 'quotes' => [
                $this->quote('ignition_coil', $sourceA, 'new', '100.00', '10.00'),
                $this->quote('ignition_coil', $sourceB, 'new', '140.00', null),
                $this->quote('ignition_coil', $sourceB, 'used', '20.00', null),
                $this->quote('ignition_coil', $sourceA, 'new', '80.00', null, 'EUR'),
                $this->quote('ignition_coil', $sourceStale, 'new', '1.00', null, 'EGP', now()->subDays(31)->toIso8601String()),
                $this->quote('spark_plug', $sourceA, 'new', '20.00', null),
                $this->quote('spark_plug', $sourceB, 'new', '30.00', null),
                $this->quote('not_recommended', $sourceA, 'new', '999.00', null),
            ]],
            'resp_prices', 'fixture-price', '/v1/responses', [],
            ['sources' => [
                ['url' => $sourceA, 'title' => 'Parts A'], ['url' => $sourceA, 'title' => 'Duplicate A'],
                ['url' => $sourceB, 'title' => 'Parts B'], ['url' => $sourceStale, 'title' => 'Stale'],
                ['url' => 'http://127.0.0.1/internal', 'title' => 'Unsafe'],
            ], 'webSearchCalls' => 2],
        ));
        $this->app->instance(WebPriceSearchProvider::class, $provider);

        $estimate = app(PriceResearchService::class)->research($report, $structured, 'safe-user');

        $this->assertNotNull($estimate);
        $this->assertSame('partial', $estimate->status);
        $this->assertSame('130.00', $estimate->total_low);
        $this->assertSame('130.00', $estimate->total_typical);
        $this->assertSame('170.00', $estimate->total_high);
        $this->assertCount(2, $estimate->lineItems);
        $this->assertSame(['new'], $estimate->lineItems->pluck('source_confidence_metadata.condition')->unique()->values()->all());
        $this->assertDatabaseCount('web_sources', 2);
        $this->assertDatabaseCount('part_price_quotes', 6);
        $this->assertSame(1, PartPriceQuote::query()->where('currency', 'EUR')->whereNull('normalized_amount')->count());
        $this->assertSame(1, PartPriceQuote::query()->where('condition', 'used')->count());
        $this->assertSame(1, $provider->calls);
        $this->assertDatabaseCount('ai_runs', 1);

        $cachedSearch = $report->priceSearches()->create(['country_code' => 'EG', 'city' => 'Cairo', 'currency' => 'EGP', 'query_json' => [], 'status' => 'running']);
        app(PriceResearchService::class)->research($report->fresh(), $structured, 'safe-user', $cachedSearch);
        $this->assertSame(1, $provider->calls);
        $this->assertDatabaseCount('ai_runs', 1);

        $provider->fail = true;
        $refresh = $report->priceSearches()->create(['country_code' => 'EG', 'city' => 'Cairo', 'currency' => 'EGP', 'query_json' => [], 'status' => 'queued']);
        $kept = app(PriceResearchService::class)->research($report->fresh(), $structured, 'safe-user', $refresh);
        $this->assertSame($estimate->id, $kept?->id);
        $this->assertSame('failed', $refresh->fresh()->status);
        $this->assertSame(2, $provider->calls);
        $this->assertSame(1, AiRun::query()->where('status', 'failed')->count());

        $provider->fail = false;
        $provider->transientFail = true;
        $retryable = $report->priceSearches()->create(['country_code' => 'EG', 'city' => 'Cairo', 'currency' => 'EGP', 'query_json' => [], 'status' => 'queued']);
        try {
            app(PriceResearchService::class)->research($report->fresh(), $structured, 'safe-user', $retryable);
            $this->fail('Expected a transient refresh failure to be retried by the queue.');
        } catch (AiProviderException $e) {
            $this->assertTrue($e->transient);
        }
        $this->assertSame($estimate->id, $report->fresh()->estimate?->id);
        $this->assertSame('failed', $retryable->fresh()->status);
    }

    public function test_no_compatible_current_price_is_unavailable_not_zero(): void
    {
        [$report, $structured] = $this->reportWithParts();
        $provider = new FixturePriceSearchProvider(new AiProviderResult(
            ['status' => 'unavailable', 'reason' => 'No current attributable price.', 'quotes' => []],
            'resp_none', 'fixture-price', '/v1/responses', [], ['sources' => [['url' => 'https://parts.example/no-price', 'title' => 'No price']]],
        ));
        $this->app->instance(WebPriceSearchProvider::class, $provider);

        $estimate = app(PriceResearchService::class)->research($report, $structured, 'safe-user');

        $this->assertSame('unavailable', $estimate?->status);
        $this->assertNull($estimate?->total_low);
        $this->assertNull($estimate?->total_typical);
        $this->assertNull($estimate?->total_high);
        $this->assertSame('unavailable', PriceSearch::query()->sole()->status);
        $this->assertSame(0, WebSource::query()->count());
    }

    public function test_configured_currency_and_labor_sources_produce_a_complete_deterministic_range(): void
    {
        [$report, $structured] = $this->reportWithParts();
        CurrencyRate::query()->create([
            'base_currency' => 'USD', 'quote_currency' => 'EGP', 'rate' => '50.0000000000',
            'provider' => 'fixture-central-bank', 'effective_at' => now()->subHour(),
        ]);
        LaborRateSource::query()->create([
            'country_code' => 'EG', 'city' => 'Cairo', 'service_category' => 'default',
            'hourly_low' => '100.00', 'hourly_typical' => '150.00', 'hourly_high' => '200.00',
            'hours_low' => '1.00', 'hours_typical' => '1.50', 'hours_high' => '2.00',
            'currency' => 'EGP', 'observed_at' => now()->subDay(), 'expires_at' => now()->addMonth(),
        ]);
        $sourceA = 'https://parts-a.example/usd-coil';
        $sourceB = 'https://parts-b.example/usd-plug';
        $provider = new FixturePriceSearchProvider(new AiProviderResult(
            ['status' => 'available', 'reason' => null, 'quotes' => [
                $this->quote('ignition_coil', $sourceA, 'new', '10.00', '2.00', 'USD'),
                $this->quote('spark_plug', $sourceB, 'new', '20.00', null, 'USD'),
            ]],
            'resp_converted', 'fixture-price', '/v1/responses', [], ['sources' => [
                ['url' => $sourceA, 'title' => 'Parts A'], ['url' => $sourceB, 'title' => 'Parts B'],
            ]],
        ));
        $this->app->instance(WebPriceSearchProvider::class, $provider);

        $estimate = app(PriceResearchService::class)->research($report, $structured, 'safe-user');

        $this->assertSame('available', $estimate?->status);
        $this->assertSame('1600.00', $estimate?->parts_low);
        $this->assertSame('100.00', $estimate?->labor_low);
        $this->assertSame('1700.00', $estimate?->total_low);
        $this->assertSame('1825.00', $estimate?->total_typical);
        $this->assertSame('2000.00', $estimate?->total_high);
        $this->assertCount(3, $estimate?->lineItems ?? []);
        $this->assertSame(0, bccomp((string) PartPriceQuote::query()->firstOrFail()->currency_rate, '50.0000000000', 10));
        $this->assertSame('fixture-central-bank', PartPriceQuote::query()->firstOrFail()->currency_rate_provider);
        $this->assertNotNull(PartPriceQuote::query()->firstOrFail()->currency_rate_effective_at);
        $this->assertSame('general_service_labor', $estimate?->lineItems->firstWhere('category', 'labor')?->canonical_code);
    }

    private function reportWithParts(): array
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $structured = FakeAiProviders::report();
        $structured['suspectedFaults'][0]['recommendedParts'] = [
            $this->part('ignition_coil', 'Ignition coil', 'ملف إشعال'),
            $this->part('spark_plug', 'Spark plug', 'شمعة إشعال'),
        ];

        /** @var DiagnosticReport $report */
        $report = app(DiagnosticReportPersister::class)->persist($session, $structured);

        return [$report, $structured];
    }

    private function part(string $canonical, string $english, string $arabic): array
    {
        return [
            'canonicalName' => $canonical, 'name' => ['en' => $english, 'ar' => $arabic],
            'reason' => ['en' => 'Inspection may identify this part.', 'ar' => 'قد يحدد الفحص هذه القطعة.'],
            'partNumber' => null, 'required' => true, 'compatibilityConfidence' => 0.7,
            'searchKeywords' => ['en' => "Toyota Corolla $english", 'ar' => "تويوتا كورولا $arabic"],
        ];
    }

    private function quote(string $part, string $url, string $condition, string $amount, ?string $shipping, string $currency = 'EGP', ?string $observedAt = null): array
    {
        return [
            'canonicalPartName' => $part, 'merchant' => parse_url($url, PHP_URL_HOST), 'condition' => $condition,
            'brandOrManufacturer' => null, 'partNumber' => null, 'amount' => $amount, 'currency' => $currency,
            'availability' => 'in_stock', 'shippingAmount' => $shipping, 'taxIncluded' => null,
            'compatibilityEvidence' => 'Listed for the supplied vehicle configuration.', 'sourceUrl' => $url,
            'sourceTitle' => 'Current listing', 'rawPriceText' => "$amount $currency", 'observedAt' => $observedAt ?? now()->toIso8601String(),
        ];
    }
}

class FixturePriceSearchProvider implements WebPriceSearchProvider
{
    public int $calls = 0;

    public bool $fail = false;

    public bool $transientFail = false;

    public function __construct(private AiProviderResult $result) {}

    public function research(array $vehicle, array $parts, array $market, string $safetyIdentifier): AiProviderResult
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('Fixture provider failure.');
        }
        if ($this->transientFail) {
            throw new AiProviderException('Fixture rate limit.', 'rate_limit', true, 2);
        }

        return $this->result;
    }
}
