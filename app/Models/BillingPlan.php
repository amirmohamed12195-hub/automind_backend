<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends UlidModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean', 'published' => 'boolean', 'recommended' => 'boolean',
            'default_for_new_users' => 'boolean', 'sort_order' => 'integer',
            'max_vehicles' => 'integer', 'reports_per_period' => 'integer',
        ];
    }

    /** @return HasMany<BillingPlanLocalization, $this> */
    public function localizations(): HasMany
    {
        return $this->hasMany(BillingPlanLocalization::class);
    }

    /** @return HasMany<BillingPlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(BillingPlanFeature::class);
    }

    /** @return HasMany<BillingPlanRegion, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(BillingPlanRegion::class);
    }

    /** @return HasMany<StoreProduct, $this> */
    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }
}
