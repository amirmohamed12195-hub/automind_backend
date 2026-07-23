<?php

namespace App\Services\Geocoding;

use App\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpGeocodingProvider implements GeocodingProvider
{
    public function geocode(string $address, string $countryCode): array
    {
        $endpoint = (string) config('services.geocoding.endpoint');
        $key = (string) config('services.geocoding.key');
        if ($endpoint === '' || $key === '') {
            throw new RuntimeException('Geocoding is not configured.');
        }
        $result = Http::acceptJson()->get($endpoint, ['address' => $address, 'country' => $countryCode, 'key' => $key])->throw()->json();
        if (! is_array($result) || ! isset($result['latitude'], $result['longitude'])) {
            throw new RuntimeException('Geocoding provider returned no coordinates.');
        }

        return ['latitude' => (float) $result['latitude'], 'longitude' => (float) $result['longitude']];
    }
}
