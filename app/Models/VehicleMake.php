<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends UlidModel
{
    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    /** @return HasMany<VehicleModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class, 'make_id');
    }
}
