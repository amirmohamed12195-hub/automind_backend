<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KashierOrder extends UlidModel
{
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'checkout_url' => 'encrypted',
            'expires_at' => 'datetime', 'paid_at' => 'datetime',
            'access_ends_at' => 'datetime', 'last_checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BillingPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }
}
