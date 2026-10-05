<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Support\VehicleDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if ($this->image_path) {
            try {
                $imageUrl = Storage::disk(config('automind.media.disk'))->temporaryUrl($this->image_path, now()->addMinutes(config('automind.media.signed_url_ttl_minutes')));
            } catch (Throwable) {
            }
        }
        $locale = app()->getLocale();
        [$catalogMake, $catalogModel] = app(VehicleDisplayName::class)->catalog($this->resource);
        $catalogImage = $catalogModel?->displayImage((int) $this->year, $catalogMake) ?? [
            'url' => $catalogMake?->logoUrl(),
            'type' => $catalogMake?->logoUrl() ? 'brand_logo' : null,
            'generation' => null,
            'attribution' => null,
        ];
        $selected = DB::table('user_selected_vehicles')->where('user_id', $this->user_id)->where('vehicle_id', $this->id)->exists();

        return [
            'id' => (string) $this->id, 'userId' => (string) $this->user_id, 'brand' => $this->brand, 'model' => $this->model,
            'displayBrand' => $catalogMake?->{"name_$locale"} ?: $this->brand, 'displayModel' => $catalogModel?->{"name_$locale"} ?: $this->model, 'displayLocale' => $locale,
            'year' => (int) $this->year, 'engine' => $this->engine, 'fuelType' => $this->fuel_type, 'transmission' => $this->transmission,
            'mileage' => (int) $this->mileage_km, 'vin' => $this->vin, 'imagePath' => $imageUrl, 'brandLogoUrl' => $catalogMake?->logoUrl(),
            'catalogImageUrl' => $catalogImage['url'], 'catalogImageType' => $catalogImage['type'],
            'catalogGenerationCode' => $catalogImage['generation']?->code, 'catalogImageAttribution' => $catalogImage['attribution'],
            'catalogMakeId' => $this->catalog_make_id ? (string) $this->catalog_make_id : null, 'catalogModelId' => $this->catalog_model_id ? (string) $this->catalog_model_id : null,
            'healthScore' => (int) $this->health_score,
            'plateNumber' => $this->plate_number, 'nickname' => $this->nickname, 'isSelected' => $selected,
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(), 'updatedAt' => $this->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
