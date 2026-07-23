<?php

namespace App\Contracts;

use App\DTO\AiProviderResult;

interface WebPriceSearchProvider
{
    public function research(array $vehicle, array $parts, array $market, string $safetyIdentifier): AiProviderResult;
}
