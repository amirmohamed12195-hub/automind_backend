<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends UlidModel
{
    protected function casts(): array
    {
        return ['requested_start_at' => 'datetime', 'requested_end_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Mechanic, $this> */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return HasOne<MechanicReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(MechanicReview::class);
    }
}
