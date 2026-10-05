<?php

namespace Tests\Unit;

use App\Support\ContentLocale;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentLocaleTest extends TestCase
{
    #[DataProvider('descriptions')]
    public function test_meaningful_description_language(?string $text, ?string $expected, array $vehicleNames = []): void
    {
        $this->assertSame($expected, app(ContentLocale::class)->detect($text, $vehicleNames));
    }

    public static function descriptions(): array
    {
        return [
            [null, null], ['', null], ['P0301 P0420 1500 RPM OBD-II', null],
            ['Toyota Corolla BMW 1HGCM82633A004352', null], ['تويوتا كورولا ٢٠١٨', null],
            ['noise', 'en'], ['صوت', 'ar'], ['Why does my engine shake?', 'en'],
            ['المحرك يهتز أثناء التوقف', 'ar'], ['العربية BMW بتقطع مع RPM عالي', 'ar'],
            ['Toyota https://example.com/engine-noise', null], ['Nissan Zafira', null, ['Nissan', 'Zafira']],
            ['P0301 and rough idle', 'en'], ['السيارة BMW تظهر P0301', 'ar'],
            ['صَوْتٌ عَالٍ', 'ar'],
        ];
    }

    public function test_technical_input_uses_explicit_supported_fallback(): void
    {
        $detector = app(ContentLocale::class);
        $this->assertSame('ar', $detector->resolve('P0301 RPM BMW', 'ar'));
        $this->assertSame('en', $detector->resolve('noise', 'ar'));
        $this->assertSame('ar', $detector->resolve('صوت المحرك', 'en'));
        $this->assertSame('en', $detector->resolve(null, 'fr'));
    }
}
