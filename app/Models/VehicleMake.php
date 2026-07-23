<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends UlidModel
{
    /** @return HasMany<VehicleModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class, 'make_id');
    }
}
