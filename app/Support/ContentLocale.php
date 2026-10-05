<?php

namespace App\Support;

use App\Models\DiagnosticSession;

/** Deterministic English/Arabic prose detection, independent of UI locale. */
class ContentLocale
{
    public const NEUTRAL_WORDS = 'obd obdii obd2 dtc vin ecu ecm pcm tcm abs esp esc srs rpm maf map egr dpf o2 can iso sae pid pids elm elm327 bluetooth wifi usb atf cvt dsg vvt vtec tsi tdi gdi fsi dohc sohc km mph kph kmh psi kpa bar v volt volts c f l ml ii iii iv toyota lexus honda acura nissan infiniti mazda subaru mitsubishi suzuki isuzu daihatsu hyundai kia genesis bmw mercedes benz audi volkswagen vw porsche skoda seat cupra renault peugeot citroen dacia fiat alfa romeo ferrari maserati volvo saab ford chevrolet chevy gmc buick cadillac chrysler dodge jeep ram tesla byd geely chery mg haval jac baic opel vauxhall land rover range jaguar mini smart corolla camry yaris rav4 hilux civic accord crv sunny sentra altima patrol qashqai elantra accent sonata tucson cerato sportage picanto optima rio golf polo passat focus fiesta تويوتا لكزس هوندا نيسان مازدا سوبارو ميتسوبيشي سوزوكي هيونداي كيا مرسيدس أودي اودي فولكس فورد شيفروليه رينو بيجو فيات فولفو جيب تسلا كورولا كامري يارس سيفيك صني سنترا النترا إلنترا اكسنت أكسنت سوناتا توسان سيراتو سبورتاج';

    public function detect(?string $text, array $vehicleNames = []): ?string
    {
        $text = preg_replace('~https?://\S+|www\.\S+|[\w.+-]+@[\w.-]+\.[a-z]+~iu', ' ', $text ?? '') ?? '';
        $text = preg_replace('/\b[A-HJ-NPR-Z0-9]{17}\b/', ' ', $text) ?? '';
        // Remove identifiers containing digits before extracting letter tokens.
        $text = preg_replace('/\b(?=[a-z0-9]*[0-9])[a-z0-9]+\b/iu', ' ', $text) ?? '';
        $ignored = array_fill_keys($this->words(self::NEUTRAL_WORDS.' '.implode(' ', $vehicleNames)), true);
        $counts = ['ar' => 0, 'en' => 0];
        foreach ($this->words($text) as $word) {
            if (mb_strlen($word) < 2 || isset($ignored[$word])) {
                continue;
            }
            $locale = preg_match('/\p{Arabic}/u', $word) ? 'ar' : 'en';
            $counts[$locale] += mb_strlen($word);
        }
        if ($counts['ar'] === 0 && $counts['en'] === 0) {
            return null;
        }

        return $counts['ar'] >= $counts['en'] ? 'ar' : 'en';
    }

    public function resolve(?string $text, ?string $fallback = null, array $vehicleNames = []): string
    {
        return $this->detect($text, $vehicleNames) ?? $this->supported($fallback);
    }

    /** Report display follows the current app locale, never the input language. */
    public function forReport(?string $override = null): string
    {
        return $this->supported($override ?? app()->getLocale());
    }

    public function sessionInput(DiagnosticSession $session): ?string
    {
        $names = [(string) $session->vehicle?->brand, (string) $session->vehicle?->model];

        return $this->detect($session->description, $names)
            ?? $this->detect(data_get($session->input_manifest, 'untrustedEvidence.spokenDescription.text'), $names);
    }

    public function supported(?string $locale): string
    {
        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }

    private function words(string $text): array
    {
        preg_match_all('/[\p{Arabic}]+|[a-z]+/u', mb_strtolower($text), $matches);

        return array_values(array_filter(array_map(
            static fn (string $word): string => preg_replace('/[^\p{L}]/u', '', $word) ?? '',
            $matches[0],
        )));
    }
}
