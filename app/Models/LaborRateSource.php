<?php

namespace App\Models;

class LaborRateSource extends UlidModel
{
    protected function casts(): array
    {
        return [
            'hourly_low' => 'decimal:2', 'hourly_typical' => 'decimal:2', 'hourly_high' => 'decimal:2',
            'hours_low' => 'decimal:2', 'hours_typical' => 'decimal:2', 'hours_high' => 'decimal:2',
            'observed_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }
}
