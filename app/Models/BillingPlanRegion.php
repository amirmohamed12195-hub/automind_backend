<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanRegion extends UlidModel
{
    protected function casts(): array
    {
        return ['visible' => 'boolean', 'available_from' => 'datetime', 'available_until' => 'datetime'];
    }

    /** @return BelongsTo<BillingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }
}
