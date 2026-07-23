<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface AudioUnderstandingProvider
{
    public function understand(string $disk, string $path, string $mimeType, string $safetyIdentifier): AiProviderResult;
}
