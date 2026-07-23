<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends UlidModel
{
    protected function casts(): array
    {
        return ['raw_usage_json' => 'array', 'response_metadata_json' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return HasMany<MediaObservation, $this> */
    public function mediaObservations(): HasMany
    {
        return $this->hasMany(MediaObservation::class);
    }
}
