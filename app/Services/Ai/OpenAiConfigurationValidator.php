<?php

namespace App\Services\Ai;

use RuntimeException;

class OpenAiConfigurationValidator
{
    public function errors(bool $requireKey = true): array
    {
        $errors = [];
        $apiKey = (string) config('openai.api_key');
        if ($requireKey && blank($apiKey)) {
            $errors[] = 'OPENAI_API_KEY is required.';
        } elseif ($requireKey && (
            str_contains($apiKey, 'OPENAI_BASE_URL=')
            || str_contains(strtoupper($apiKey), 'CHANGE_ME')
            || str_contains(strtoupper($apiKey), 'REPLACE_WITH')
        )) {
            $errors[] = 'OPENAI_API_KEY is malformed or still contains a placeholder.';
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
        if (! in_array(config('openai.text_verbosity'), ['low', 'medium', 'high'], true)) {
            $errors[] = 'OPENAI_TEXT_VERBOSITY must be low, medium, or high.';
        }
        foreach (['diagnosis', 'vision', 'price_search'] as $task) {
            $effort = config("openai.{$task}_reasoning_effort");
            if (! in_array($effort, ['none', 'low', 'medium', 'high', 'xhigh', 'max'], true)) {
                $errors[] = 'OPENAI_'.strtoupper($task).'_REASONING_EFFORT must be none, low, medium, high, xhigh, or max.';
            }
        }
        $requestTimeout = (int) config('openai.timeout_seconds');
        $connectTimeout = (int) config('openai.connect_timeout_seconds');
        if ($requestTimeout < 10 || $requestTimeout > 180) {
            $errors[] = 'OPENAI_REQUEST_TIMEOUT_SECONDS must be between 10 and 180.';
        }
        if ($connectTimeout < 1 || $connectTimeout > $requestTimeout) {
            $errors[] = 'OPENAI_CONNECT_TIMEOUT_SECONDS must be between 1 and OPENAI_REQUEST_TIMEOUT_SECONDS.';
        }
        foreach ([
            'OPENAI_DIAGNOSIS_MAX_OUTPUT_TOKENS' => 'diagnosis_max_output_tokens',
            'OPENAI_VISION_MAX_OUTPUT_TOKENS' => 'vision_max_output_tokens',
            'OPENAI_AUDIO_MAX_OUTPUT_TOKENS' => 'audio_max_output_tokens',
            'OPENAI_PRICE_SEARCH_MAX_OUTPUT_TOKENS' => 'price_search_max_output_tokens',
            'OPENAI_REPORT_ASSISTANT_MAX_OUTPUT_TOKENS' => 'report_assistant_max_output_tokens',
        ] as $environmentName => $configKey) {
            $tokens = (int) config("openai.$configKey");
            if ($tokens < 128 || $tokens > 20000) {
                $errors[] = "$environmentName must be between 128 and 20000.";
            }
        }
        if ($requireKey && config('openai.pricing.version') === 'unconfigured') {
            $errors[] = 'OPENAI_PRICING_VERSION must identify the official pricing snapshot.';
        }
        if ($requireKey) {
            $pricingModels = config('openai.pricing.models', []);
            foreach (array_unique([(string) config('openai.diagnosis_model'), (string) config('openai.vision_model'), (string) config('openai.audio_model'), (string) config('openai.transcription_model'), (string) config('openai.price_search_model')]) as $model) {
                $rates = is_array($pricingModels) ? ($pricingModels[$model] ?? null) : null;
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
