<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ObjectStorageProvider;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\DeviceToken;
use App\Services\AccountDeletionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AccountController
{
    public function show(Request $request)
    {
        return ApiResponse::success((new UserResource($request->user()))->resolve());
    }

    public function update(UpdateProfileRequest $request)
    {
        $map = ['name' => 'name', 'email' => 'email', 'phone' => 'phone', 'countryCode' => 'country_code', 'city' => 'city', 'latitude' => 'latitude', 'longitude' => 'longitude', 'currency' => 'currency'];
        $attributes = [];
        foreach ($map as $external => $column) {
            if ($request->exists($external)) {
                $attributes[$column] = is_string($request->input($external)) ? trim($request->input($external)) : $request->input($external);
            }
        }
        if (isset($attributes['country_code'])) {
            $attributes['country_code'] = strtoupper($attributes['country_code']);
        }
        if (isset($attributes['currency'])) {
            $attributes['currency'] = strtoupper($attributes['currency']);
        }
        $request->user()->update($attributes);

        return ApiResponse::success((new UserResource($request->user()->fresh()))->resolve());
    }

    public function uploadAvatar(Request $request, ObjectStorageProvider $storage)
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000']]);
        $stored = $storage->storePrivate($request->file('avatar'), 'avatars/'.$request->user()->id);
        if ($request->user()->avatar_path) {
            $storage->delete(config('automind.media.disk'), $request->user()->avatar_path);
        }
        $request->user()->update(['avatar_path' => $stored['path']]);

        return ApiResponse::success((new UserResource($request->user()->fresh()))->resolve());
    }

    public function deleteAvatar(Request $request, ObjectStorageProvider $storage)
    {
        if ($request->user()->avatar_path) {
            $storage->delete(config('automind.media.disk'), $request->user()->avatar_path);
        }
        $request->user()->update(['avatar_path' => null]);

        return response()->noContent();
    }

    public function destroy(Request $request, AccountDeletionService $deletion)
    {
        $deletion->request($request->user());

        return response()->noContent();
    }

    public function settings(Request $request)
    {
        return ApiResponse::success(['locale' => $request->user()->locale, 'themeMode' => $request->user()->theme_mode, 'units' => $request->user()->units, 'maintenanceRemindersEnabled' => (bool) $request->user()->maintenance_reminders_enabled]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['locale' => ['sometimes', 'in:en,ar'], 'themeMode' => ['sometimes', 'in:system,light,dark'], 'units' => ['sometimes', 'in:metric,imperial'], 'maintenanceRemindersEnabled' => ['sometimes', 'boolean']]);
        $request->user()->update(['locale' => $data['locale'] ?? $request->user()->locale, 'theme_mode' => $data['themeMode'] ?? $request->user()->theme_mode, 'units' => $data['units'] ?? $request->user()->units, 'maintenance_reminders_enabled' => $data['maintenanceRemindersEnabled'] ?? $request->user()->maintenance_reminders_enabled]);

        return $this->settings($request);
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate(['platform' => ['required', 'in:ios,android,web'], 'token' => ['required', 'string', 'max:4096'], 'deviceName' => ['nullable', 'string', 'max:120'], 'appVersion' => ['nullable', 'string', 'max:32']]);
        $hash = hash('sha256', $data['token']);
        $device = DeviceToken::query()->updateOrCreate(['token_hash' => $hash], ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'push_token' => $data['token'], 'device_name' => $data['deviceName'] ?? null, 'app_version' => $data['appVersion'] ?? null, 'last_seen_at' => now(), 'enabled' => true]);

        return ApiResponse::success(['id' => (string) $device->id, 'platform' => $device->platform, 'enabled' => true], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteDevice(Request $request, string $deviceTokenId)
    {
        DeviceToken::query()->where('user_id', $request->user()->id)->findOrFail($deviceTokenId)->delete();

        return response()->noContent();
    }
}
