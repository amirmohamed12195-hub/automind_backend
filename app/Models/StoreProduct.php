<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreProduct extends UlidModel
{
    protected function casts(): array
    {
        return [
            'active_for_sale' => 'boolean', 'effective_from' => 'datetime',
            'effective_until' => 'datetime', 'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BillingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    /** @return HasMany<StorePriceSnapshot, $this> */
    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(StorePriceSnapshot::class);
    }

    /** @return HasMany<StorePurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(StorePurchase::class);
    }
}
