<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePriceSnapshot extends UlidModel
{
    protected function casts(): array
    {
        return ['offer_summary' => 'array', 'fetched_at' => 'datetime'];
    }

    /** @return BelongsTo<StoreProduct, $this> */
    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }
}
