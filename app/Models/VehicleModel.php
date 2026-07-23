<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleModel extends UlidModel
{
    protected $table = 'vehicle_models';

    /** @return BelongsTo<VehicleMake, $this> */
    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }
}
