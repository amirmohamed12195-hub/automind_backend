<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface ReportAssistantProvider
{
    /** @param array<int, array<string, mixed>> $images */
    public function answer(array $reportContext, ?string $question, array $images, string $safetyIdentifier): AiProviderResult;
}
