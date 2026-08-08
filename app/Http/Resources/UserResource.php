<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $avatarUrl = null;
        if ($this->avatar_path) {
            try {
                $avatarUrl = Storage::disk(config('automind.media.disk'))->temporaryUrl($this->avatar_path, now()->addMinutes(config('automind.media.signed_url_ttl_minutes')));
            } catch (Throwable) {
            }
        }

        return [
            'id' => (string) $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'avatarUrl' => $avatarUrl, 'locale' => $this->locale ?: 'en', 'themeMode' => $this->theme_mode ?: 'system', 'units' => $this->units ?: 'metric',
            'countryCode' => $this->country_code, 'city' => $this->city, 'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null, 'currency' => $this->currency,
            'maintenanceRemindersEnabled' => $this->maintenance_reminders_enabled === null ? true : (bool) $this->maintenance_reminders_enabled,
            'createdAt' => $this->created_at?->utc()->toIso8601ZuluString(), 'updatedAt' => $this->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
