<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserEntitlement extends UlidModel
{
    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime', 'period_start' => 'datetime', 'period_end' => 'datetime',
            'auto_renew_enabled' => 'boolean', 'grace_period_end' => 'datetime',
            'canceled_at' => 'datetime', 'revoked_at' => 'datetime', 'refunded_at' => 'datetime',
            'last_verified_at' => 'datetime',
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

    /** @return BelongsTo<StorePurchase, $this> */
    public function storePurchase(): BelongsTo
    {
        return $this->belongsTo(StorePurchase::class);
    }

    /** @return HasMany<EntitlementPeriodUsage, $this> */
    public function usagePeriods(): HasMany
    {
        return $this->hasMany(EntitlementPeriodUsage::class);
    }
}
