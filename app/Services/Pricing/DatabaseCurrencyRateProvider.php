<?php

namespace App\Services\Pricing;

use App\Contracts\CurrencyRateProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseCurrencyRateProvider implements CurrencyRateProvider
{
    public function conversion(string $baseCurrency, string $quoteCurrency): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $quoteCurrency = strtoupper($quoteCurrency);
        if ($baseCurrency === $quoteCurrency) {
            return ['rate' => '1.0000000000', 'provider' => 'identity', 'effectiveAt' => now()->toIso8601String()];
        }
        $rate = DB::table('currency_rates')->where('base_currency', $baseCurrency)->where('quote_currency', $quoteCurrency)->where('effective_at', '<=', now())->latest('effective_at')->first(['rate', 'provider', 'effective_at']);
        if ($rate === null) {
            throw new RuntimeException('No current currency rate is configured.');
        }

        return ['rate' => (string) $rate->rate, 'provider' => (string) $rate->provider, 'effectiveAt' => (string) $rate->effective_at];
    }
}
