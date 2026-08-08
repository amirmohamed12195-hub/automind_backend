<?php

namespace App\Services\Ai;

use App\Contracts\AudioUnderstandingProvider;
use App\DTO\AiProviderResult;
use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Storage;
use JsonException;

class OpenAiAudioUnderstandingProvider implements AudioUnderstandingProvider
{
    public function __construct(private OpenAiHttpTransport $transport) {}

    public function understand(string $disk, string $path, string $mimeType, string $safetyIdentifier): AiProviderResult
    {
        $format = match ($mimeType) {
            'audio/wav', 'audio/x-wav' => 'wav', 'audio/mpeg' => 'mp3', default => 'm4a'
        };
        $response = $this->transport->post('/chat/completions', [
            'model' => config('openai.audio_model'), 'safety_identifier' => $safetyIdentifier,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Analyze this as consumer-recorded engine sound, not speech. Return cautious acoustic observations only (rhythmic clicking, knocking-like impulses, squeal-like tones, rough idle variation, or insufficient quality). Audio alone cannot prove a component failure. Return only one JSON object with exactly these keys: {"quality":"poor|limited|moderate|strong","observations":[{"code":"short_snake_case","confidence":0.0,"textEn":"English observation","textAr":"Arabic observation"}]}. Include at most 10 observations and never use confidence above 0.75.'],
                ['type' => 'input_audio', 'input_audio' => ['data' => base64_encode(Storage::disk($disk)->get($path)), 'format' => $format]],
            ]]],
            'max_completion_tokens' => min(1000, (int) config('openai.max_output_tokens')),
        ]);
        if (isset($response['choices'][0]['message']['refusal'])) {
            throw new AiProviderException('The AI provider declined audio analysis.', 'refusal');
        }
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new AiProviderException('Audio analysis returned no structured output.', 'schema');
        }
        try {
            $data = json_decode($this->jsonContent($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiProviderException('Audio analysis returned invalid structured output.', 'schema');
        }
        if (! is_array($data) || ! $this->isValidResult($data)) {
            throw new AiProviderException('Audio analysis returned invalid structured output.', 'schema');
        }

        return new AiProviderResult($data, $response['id'] ?? null, $response['model'] ?? config('openai.audio_model'), '/v1/chat/completions', $response['usage'] ?? []);
    }

    private function jsonContent(string $content): string
    {
        $trimmed = trim($content);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    private function isValidResult(array $data): bool
    {
        if (count($data) !== 2
            || ! array_key_exists('quality', $data)
            || ! array_key_exists('observations', $data)
            || ! in_array($data['quality'], ['poor', 'limited', 'moderate', 'strong'], true)
            || ! is_array($data['observations'])
            || count($data['observations']) > 10) {
            return false;
        }
        foreach ($data['observations'] as $observation) {
            if (! is_array($observation)
                || count($observation) !== 4
                || ! array_key_exists('code', $observation)
                || ! array_key_exists('confidence', $observation)
                || ! array_key_exists('textEn', $observation)
                || ! array_key_exists('textAr', $observation)
                || ! is_string($observation['code'])
                || ! is_numeric($observation['confidence'])
                || (float) $observation['confidence'] < 0
                || (float) $observation['confidence'] > 0.75
                || ! is_string($observation['textEn'])
                || ! is_string($observation['textAr'])) {
                return false;
            }
        }

        return true;
    }
}
