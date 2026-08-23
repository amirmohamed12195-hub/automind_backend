<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property float|null $distance_km */
class Mechanic extends UlidModel
{
    public function serviceRequestInvitationStatus(): ?string
    {
        $pivot = $this->getRelationValue('pivot');

        return $pivot instanceof Pivot ? (string) $pivot->getAttribute('status') : null;
    }

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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<ServiceRequest, $this> */
    public function serviceRequests(): BelongsToMany
    {
        return $this->belongsToMany(ServiceRequest::class, 'service_request_mechanic')
            ->withPivot('status')->withTimestamps();
    }
}
