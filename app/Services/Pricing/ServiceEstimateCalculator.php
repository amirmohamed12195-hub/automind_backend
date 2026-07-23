<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class ServiceEstimateCalculator
{
    public function calculate(array $lineItems): array
    {
        $totals = ['low' => '0.00', 'typical' => '0.00', 'high' => '0.00'];
        $currency = null;
        foreach ($lineItems as $item) {
            $currency ??= strtoupper($item['currency']);
            if ($currency !== strtoupper($item['currency'])) {
                throw new InvalidArgumentException('All estimate items must use one currency.');
            }
            foreach (array_keys($totals) as $band) {
                $amount = (string) ($item[$band] ?? '0');
                if (bccomp($amount, '0', 4) < 0) {
                    throw new InvalidArgumentException('Estimate amounts cannot be negative.');
                }
                $totals[$band] = bcadd($totals[$band], bcmul($amount, (string) ($item['quantity'] ?? '1'), 4), 2);
            }
        }
        if (bccomp($totals['low'], $totals['typical'], 2) > 0 || bccomp($totals['typical'], $totals['high'], 2) > 0) {
            throw new InvalidArgumentException('Estimate range must be ordered low, typical, high.');
        }

        return ['currency' => $currency, ...$totals];
    }
}
