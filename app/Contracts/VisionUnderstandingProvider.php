<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface VisionUnderstandingProvider
{
    public function observe(array $media, string $safetyIdentifier): AiProviderResult;
}
