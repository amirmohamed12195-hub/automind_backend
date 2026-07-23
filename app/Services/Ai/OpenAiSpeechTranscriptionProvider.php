<?php

namespace App\Services\Ai;

use App\Contracts\SpeechTranscriptionProvider;
use App\DTO\AiProviderResult;
use Illuminate\Support\Facades\Storage;

class OpenAiSpeechTranscriptionProvider implements SpeechTranscriptionProvider
{
    public function __construct(private OpenAiHttpTransport $transport) {}

    public function transcribe(string $disk, string $path, string $mimeType, ?string $language): AiProviderResult
    {
        $stream = Storage::disk($disk)->readStream($path);
        $parts = [
            ['name' => 'model', 'contents' => config('openai.transcription_model')],
            ['name' => 'response_format', 'contents' => 'json'],
            ['name' => 'file', 'contents' => $stream, 'filename' => basename($path), 'headers' => ['Content-Type' => $mimeType]],
        ];
        if (in_array($language, ['en', 'ar'], true)) {
            $parts[] = ['name' => 'language', 'contents' => $language];
        }
        try {
            $response = $this->transport->multipart('/audio/transcriptions', $parts);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $detectedLanguage = $response['language'] ?? $language;
        $quality = $response['quality'] ?? 'unknown';

        return new AiProviderResult(
            ['text' => (string) ($response['text'] ?? ''), 'language' => $detectedLanguage, 'quality' => $quality, 'confidence' => $response['confidence'] ?? null],
            $response['id'] ?? null,
            config('openai.transcription_model'),
            '/v1/audio/transcriptions',
            $response['usage'] ?? [],
            ['language' => $detectedLanguage, 'quality' => $quality],
        );
    }
}
