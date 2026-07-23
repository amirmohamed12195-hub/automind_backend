<?php

namespace App\Services\Ai;

class AiCostCalculator
{
    public function estimate(string $model, array $usage, array $metadata = []): ?string
    {
        $rates = config("openai.pricing.models.$model");
        if (! is_array($rates)) {
            return null;
        }
        foreach (['input', 'output', 'cachedInput', 'webSearchCall'] as $key) {
            if (array_key_exists($key, $rates) && ! $this->isDecimal($rates[$key])) {
                return null;
            }
        }

        $input = max(0, (int) ($usage['inputTokens'] ?? $usage['prompt_tokens'] ?? 0));
        $cached = min($input, max(0, (int) ($usage['cachedTokens'] ?? $usage['prompt_tokens_details']['cached_tokens'] ?? 0)));
        $output = max(0, (int) ($usage['outputTokens'] ?? $usage['completion_tokens'] ?? 0));
        $webSearchCalls = max(0, (int) ($metadata['webSearchCalls'] ?? 0));

        $inputCost = bcdiv(bcmul((string) ($input - $cached), (string) ($rates['input'] ?? '0'), 12), '1000000', 12);
        $cachedCost = bcdiv(bcmul((string) $cached, (string) ($rates['cachedInput'] ?? $rates['input'] ?? '0'), 12), '1000000', 12);
        $outputCost = bcdiv(bcmul((string) $output, (string) ($rates['output'] ?? '0'), 12), '1000000', 12);
        $searchCost = bcmul((string) $webSearchCalls, (string) ($rates['webSearchCall'] ?? '0'), 12);
        $cost = bcadd(bcadd($inputCost, $cachedCost, 12), bcadd($outputCost, $searchCost, 12), 12);

        return bcadd($cost, '0', 6);
    }

    private function isDecimal(mixed $value): bool
    {
        return (is_string($value) || is_int($value) || is_float($value)) && preg_match('/^\d+(?:\.\d+)?$/', (string) $value) === 1;
    }
}
