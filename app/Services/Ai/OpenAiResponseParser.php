<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use JsonException;

class OpenAiResponseParser
{
    public function structured(array $response): array
    {
        if (($response['status'] ?? null) === 'incomplete') {
            throw new AiProviderException('OpenAI response was incomplete.', 'incomplete', true);
        }
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new AiProviderException('The AI provider declined this analysis.', 'refusal', false);
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    try {
                        $decoded = json_decode($content['text'], true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        throw new AiProviderException('OpenAI structured output was invalid JSON.', 'schema', false);
                    }
                    if (! is_array($decoded)) {
                        break;
                    }

                    return $decoded;
                }
            }
        }
        throw new AiProviderException('OpenAI response contained no structured output.', 'schema', false);
    }

    public function usage(array $response): array
    {
        $usage = $response['usage'] ?? [];

        return [
            'inputTokens' => $usage['input_tokens'] ?? null,
            'outputTokens' => $usage['output_tokens'] ?? null,
            'cachedTokens' => $usage['input_tokens_details']['cached_tokens'] ?? null,
            'reasoningTokens' => $usage['output_tokens_details']['reasoning_tokens'] ?? null,
        ];
    }

    public function sources(array $response): array
    {
        $sources = [];
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                foreach ($item['action']['sources'] ?? [] as $source) {
                    if (is_array($source) && isset($source['url'])) {
                        $sources[] = $source;
                    }
                }
            }
        }

        return $sources;
    }
}
