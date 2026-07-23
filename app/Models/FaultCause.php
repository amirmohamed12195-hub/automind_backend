<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FaultCause extends UlidModel
{
    /** @return HasMany<FaultCauseTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(FaultCauseTranslation::class);
    }
}
