<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPlanLocalization extends UlidModel
{
    protected function casts(): array
    {
        return ['feature_copy_json' => 'array'];
    }

    /** @return BelongsTo<BillingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }
}
