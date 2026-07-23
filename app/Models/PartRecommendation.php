<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PartRecommendation extends UlidModel
{
    protected function casts(): array
    {
        return ['required' => 'boolean', 'compatibility_confidence' => 'float'];
    }

    /** @return HasMany<PartRecommendationTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PartRecommendationTranslation::class);
    }

    /** @return HasMany<PartPriceQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(PartPriceQuote::class);
    }
}
