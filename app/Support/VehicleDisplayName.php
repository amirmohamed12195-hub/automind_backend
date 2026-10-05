<?php

namespace App\Support;

use App\Models\Vehicle;
use App\Models\VehicleMake;

class VehicleDisplayName
{
    public function catalog(Vehicle $vehicle): array
    {
        $make = $vehicle->catalogMake;
        if (! $make || ! $this->matches($make, $vehicle->brand)) {
            $make = VehicleMake::query()->where('name_en', $vehicle->brand)->orWhere('name_ar', $vehicle->brand)->orWhere('code', str($vehicle->brand)->slug()->toString())->first();
        }
        $model = $vehicle->catalogModel;
        if (! $model || ! $this->matches($model, $vehicle->model) || $model->make_id !== $make?->id) {
            $model = $make?->models()->where(fn ($q) => $q->where('name_en', $vehicle->model)->orWhere('name_ar', $vehicle->model)->orWhere('code', str($vehicle->model)->slug()->toString()))->with('generations')->first();
        }

        return [$make, $model];
    }

    public function name(Vehicle $vehicle, string $locale): string
    {
        [$make, $model] = $this->catalog($vehicle);

        return trim(($make?->{"name_$locale"} ?: $vehicle->brand).' '.($model?->{"name_$locale"} ?: $vehicle->model));
    }

    private function matches($catalog, string $value): bool
    {
        return strcasecmp($catalog->name_en, $value) === 0 || $catalog->name_ar === $value || $catalog->code === str($value)->slug()->toString();
    }
}
