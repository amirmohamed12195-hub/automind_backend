<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
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
        $selected = DB::table('user_selected_vehicles')->where('user_id', $this->user_id)->where('vehicle_id', $this->id)->exists();

        return [
            'id' => (string) $this->id, 'userId' => (string) $this->user_id, 'brand' => $this->brand, 'model' => $this->model,
            'year' => (int) $this->year, 'engine' => $this->engine, 'fuelType' => $this->fuel_type, 'transmission' => $this->transmission,
            'mileage' => (int) $this->mileage_km, 'vin' => $this->vin, 'imagePath' => $imageUrl, 'healthScore' => (int) $this->health_score,
            'plateNumber' => $this->plate_number, 'nickname' => $this->nickname, 'isSelected' => $selected,
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(), 'updatedAt' => $this->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
