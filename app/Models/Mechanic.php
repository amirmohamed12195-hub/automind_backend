<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property float|null $distance_km */
class Mechanic extends UlidModel
{
    protected function casts(): array
    {
        return ['working_hours_json' => 'array', 'verified' => 'boolean', 'active' => 'boolean', 'rating_average' => 'float'];
    }

    /** @return BelongsToMany<MechanicSpecialty, $this> */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(MechanicSpecialty::class, 'mechanic_specialty_assignments')->withTimestamps();
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
