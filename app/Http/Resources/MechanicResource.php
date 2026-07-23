<?php

namespace App\Http\Resources;

use App\Models\Mechanic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mechanic */
class MechanicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => (string) $this->id, 'name' => $locale === 'ar' ? $this->name_ar : $this->name_en,
            'description' => $locale === 'ar' ? $this->description_ar : $this->description_en,
            'phone' => $this->phone, 'email' => $this->email, 'address' => $this->address, 'city' => $this->city, 'countryCode' => $this->country_code,
            'latitude' => (float) $this->latitude, 'longitude' => (float) $this->longitude,
            'distanceKm' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'rating' => (float) $this->rating_average, 'ratingCount' => (int) $this->rating_count, 'verified' => (bool) $this->verified,
            'workingHours' => $this->working_hours_json,
            'specialties' => $this->whenLoaded('specialties', fn () => $this->specialties->map(fn ($s) => ['code' => $s->code, 'name' => $locale === 'ar' ? $s->name_ar : $s->name_en])->all(), []),
        ];
    }
}
