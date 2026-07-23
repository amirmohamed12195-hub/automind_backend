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
        $schema = ['name' => 'automind_engine_audio_observations', 'strict' => true, 'schema' => [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['quality', 'observations'], 'properties' => [
                'quality' => ['type' => 'string', 'enum' => ['poor', 'limited', 'moderate', 'strong']],
                'observations' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['code', 'confidence', 'textEn', 'textAr'], 'properties' => ['code' => ['type' => 'string'], 'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 0.75], 'textEn' => ['type' => 'string'], 'textAr' => ['type' => 'string']]]],
            ],
        ]];
        $response = $this->transport->post('/chat/completions', [
            'model' => config('openai.audio_model'), 'safety_identifier' => $safetyIdentifier,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Analyze this as consumer-recorded engine sound, not speech. Return cautious acoustic observations only (rhythmic clicking, knocking-like impulses, squeal-like tones, rough idle variation, or insufficient quality). Audio alone cannot prove a component failure.'],
                ['type' => 'input_audio', 'input_audio' => ['data' => base64_encode(Storage::disk($disk)->get($path)), 'format' => $format]],
            ]]],
            'response_format' => ['type' => 'json_schema', 'json_schema' => $schema],
            'max_completion_tokens' => config('openai.max_output_tokens'),
        ]);
        if (isset($response['choices'][0]['message']['refusal'])) {
            throw new AiProviderException('The AI provider declined audio analysis.', 'refusal');
        }
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new AiProviderException('Audio analysis returned no structured output.', 'schema');
        }
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiProviderException('Audio analysis returned invalid structured output.', 'schema');
        }
        if (! is_array($data)) {
            throw new AiProviderException('Audio analysis returned invalid structured output.', 'schema');
        }

        return new AiProviderResult($data, $response['id'] ?? null, $response['model'] ?? config('openai.audio_model'), '/v1/chat/completions', $response['usage'] ?? []);
    }
}
