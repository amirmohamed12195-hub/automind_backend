<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntitlementPeriodUsage extends UlidModel
{
    protected $table = 'entitlement_period_usage';

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime', 'period_end' => 'datetime', 'report_limit' => 'integer',
            'reports_used' => 'integer', 'reports_reserved' => 'integer',
        ];
    }

    /** @return BelongsTo<UserEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(UserEntitlement::class, 'user_entitlement_id');
    }
}
