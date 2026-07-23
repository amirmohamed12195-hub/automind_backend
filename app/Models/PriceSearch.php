<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceSearch extends UlidModel
{
    protected function casts(): array
    {
        return ['query_json' => 'array', 'searched_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return HasMany<WebSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(WebSource::class);
    }
}
