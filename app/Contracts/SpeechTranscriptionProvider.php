<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface SpeechTranscriptionProvider
{
    public function transcribe(string $disk, string $path, string $mimeType, ?string $language): AiProviderResult;
}
