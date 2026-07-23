<?php

namespace App\Contracts;

interface CurrencyRateProvider
{
    /** @return array{rate: string, provider: string, effectiveAt: string} */
    public function conversion(string $baseCurrency, string $quoteCurrency): array;
}
