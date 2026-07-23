<?php

namespace App\Models;

class CurrencyRate extends UlidModel
{
    protected function casts(): array
    {
        return ['rate' => 'decimal:10', 'effective_at' => 'datetime'];
    }
}
