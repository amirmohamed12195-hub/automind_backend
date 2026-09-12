<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VehicleModel extends UlidModel
{
    protected $table = 'vehicle_models';

    protected static function booted(): void
    {
        static::created(function (VehicleModel $model): void {
            if (! self::generationCatalogAvailable()) {
                return;
            }

            $model->generations()->create([
                'code' => 'default',
                'name' => $model->name_en,
                'start_year' => $model->start_year,
                'end_year' => $model->end_year,
                'data_source' => 'catalog-range',
            ]);
        });
    }

    /** @return BelongsTo<VehicleMake, $this> */
    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    /** @return HasMany<VehicleModelGeneration, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(VehicleModelGeneration::class, 'model_id');
    }

    public function generationForYear(?int $year): ?VehicleModelGeneration
    {
        if (! $this->relationLoaded('generations') && ! self::generationCatalogAvailable()) {
            return null;
        }

        /** @var Collection<int, VehicleModelGeneration> $generations */
        $generations = $this->relationLoaded('generations')
            ? $this->generations
            : $this->generations()->get();

        if ($year !== null) {
            $generations = $generations->filter(fn (VehicleModelGeneration $generation): bool => $generation->includesYear($year));
        }

        return $generations->sort(function (VehicleModelGeneration $left, VehicleModelGeneration $right): int {
            $imageComparison = ((int) ! $left->image_path) <=> ((int) ! $right->image_path);
            if ($imageComparison !== 0) {
                return $imageComparison;
            }

            $yearComparison = ($right->start_year ?? 0) <=> ($left->start_year ?? 0);

            return $yearComparison !== 0 ? $yearComparison : $left->name <=> $right->name;
        })->first();
    }

    public function displayImage(?int $year = null, ?VehicleMake $make = null): array
    {
        $generation = $this->generationForYear($year);
        $imageUrl = $generation?->imageUrl();

        if ($imageUrl) {
            return [
                'url' => $imageUrl,
                'type' => 'model_photo',
                'generation' => $generation,
                'attribution' => $generation->attribution(),
            ];
        }

        $make ??= $this->relationLoaded('make') ? $this->make : $this->make()->first();

        return [
            'url' => $make?->logoUrl(),
            'type' => $make?->logoUrl() ? 'brand_logo' : null,
            'generation' => $generation,
            'attribution' => null,
        ];
    }

    public static function generationCatalogAvailable(): bool
    {
        return Schema::hasTable('vehicle_model_generations');
    }
}
