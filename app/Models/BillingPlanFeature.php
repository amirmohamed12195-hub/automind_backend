<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanFeature extends UlidModel
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'limit_value' => 'integer', 'configuration_json' => 'array'];
    }

    /** @return BelongsTo<BillingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }
}
