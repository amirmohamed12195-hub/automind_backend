<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ObdSnapshot extends UlidModel
{
    protected function casts(): array
    {
        return ['raw_json' => 'array', 'recorded_at' => 'datetime'];
    }

    /** @return HasMany<ObdTroubleCode, $this> */
    public function troubleCodes(): HasMany
    {
        return $this->hasMany(ObdTroubleCode::class);
    }
}
