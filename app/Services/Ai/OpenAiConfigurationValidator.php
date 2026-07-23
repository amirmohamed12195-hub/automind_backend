<?php

namespace App\Services\Ai;

use RuntimeException;

class OpenAiConfigurationValidator
{
    public function errors(bool $requireKey = true): array
    {
        $errors = [];
        if ($requireKey && blank(config('openai.api_key'))) {
            $errors[] = 'OPENAI_API_KEY is required.';
        }
        $baseUrl = (string) config('openai.base_url');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || (app()->environment('production') && ! str_starts_with($baseUrl, 'https://'))) {
            $errors[] = 'OPENAI_BASE_URL must be a valid HTTPS URL in production.';
        }

        foreach ([
            'diagnosis_model' => 'responses', 'vision_model' => 'vision', 'audio_model' => 'audio_input',
            'transcription_model' => 'transcription', 'price_search_model' => 'web_search',
        ] as $modelKey => $capability) {
            $model = (string) config("openai.$modelKey");
            $allowed = config("openai.capabilities.$capability", []);
            if ($model === '' || ! is_array($allowed) || ! in_array($model, $allowed, true)) {
                $errors[] = strtoupper("OPENAI_$modelKey")." [$model] is not declared as $capability capable.";
            }
        }

        if (config('openai.background_mode')) {
            $errors[] = 'OPENAI_BACKGROUND_MODE must remain false; Laravel queues own workflow orchestration and provider background-response retrieval is not enabled.';
        }
        if (! in_array(config('openai.vision_detail'), ['low', 'high', 'auto', 'original'], true)) {
            $errors[] = 'OPENAI_VISION_DETAIL must be low, high, auto, or original.';
        }
        if ($requireKey && config('openai.pricing.version') === 'unconfigured') {
            $errors[] = 'OPENAI_PRICING_VERSION must identify the official pricing snapshot.';
        }
        if ($requireKey) {
            foreach (array_unique([(string) config('openai.diagnosis_model'), (string) config('openai.vision_model'), (string) config('openai.audio_model'), (string) config('openai.transcription_model'), (string) config('openai.price_search_model')]) as $model) {
                $rates = config("openai.pricing.models.$model");
                if (! is_array($rates)) {
                    $errors[] = "Pricing rates are missing for model [$model].";

                    continue;
                }
                $requiredRates = ['input', 'output'];
                if ($model === config('openai.price_search_model')) {
                    $requiredRates[] = 'webSearchCall';
                }
                foreach ($requiredRates as $rate) {
                    $value = $rates[$rate] ?? null;
                    if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || preg_match('/^\d+(?:\.\d+)?$/', (string) $value) !== 1) {
                        $errors[] = "Pricing rate [$rate] for model [$model] must be a non-negative decimal string or number.";
                    }
                }
            }
        }

        return $errors;
    }

    public function validate(bool $requireKey = true): void
    {
        if ($errors = $this->errors($requireKey)) {
            throw new RuntimeException("Invalid OpenAI API configuration:\n- ".implode("\n- ", $errors));
        }
    }
}
