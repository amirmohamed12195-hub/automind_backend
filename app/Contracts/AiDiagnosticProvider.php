<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface AiDiagnosticProvider
{
    public function synthesize(array $evidenceManifest, string $safetyIdentifier): AiProviderResult;
}
