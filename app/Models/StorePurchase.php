<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePurchase extends UlidModel
{
    protected function casts(): array
    {
        return [
            'purchase_token' => 'encrypted', 'raw_reference' => 'encrypted:array',
            'acknowledged' => 'boolean', 'consumed' => 'boolean',
            'purchased_at' => 'datetime', 'expires_at' => 'datetime', 'last_verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StoreProduct, $this> */
    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }
}
