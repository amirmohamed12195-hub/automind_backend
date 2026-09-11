<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VehicleModelGeneration extends UlidModel
{
    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
            'image_width' => 'integer',
            'image_height' => 'integer',
            'image_last_attempted_at' => 'datetime',
            'image_downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VehicleModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function includesYear(int $year): bool
    {
        return ($this->start_year === null || $this->start_year <= $year)
            && ($this->end_year === null || $this->end_year >= $year);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_disk || ! $this->image_path) {
            return null;
        }

        try {
            return Storage::disk($this->image_disk)->url($this->image_path);
        } catch (Throwable) {
            return null;
        }
    }

    public function attribution(): ?array
    {
        if (! $this->image_path) {
            return null;
        }

        return [
            'text' => $this->image_attribution,
            'author' => $this->image_author,
            'license' => $this->image_license,
            'licenseUrl' => $this->image_license_url,
            'sourceUrl' => $this->image_source_page_url,
        ];
    }
}
