<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $total_low
 * @property string|null $total_typical
 * @property string|null $total_high
 */
class ServiceEstimate extends UlidModel
{
    protected function casts(): array
    {
        return [
            'assumptions_json' => 'array', 'searched_at' => 'datetime', 'expires_at' => 'datetime', 'confidence' => 'float',
            'parts_low' => 'decimal:2', 'parts_typical' => 'decimal:2', 'parts_high' => 'decimal:2',
            'labor_low' => 'decimal:2', 'labor_typical' => 'decimal:2', 'labor_high' => 'decimal:2',
            'fees_low' => 'decimal:2', 'fees_typical' => 'decimal:2', 'fees_high' => 'decimal:2',
            'total_low' => 'decimal:2', 'total_typical' => 'decimal:2', 'total_high' => 'decimal:2',
        ];
    }

    /** @return HasMany<ServiceEstimateLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ServiceEstimateLineItem::class);
    }
}
