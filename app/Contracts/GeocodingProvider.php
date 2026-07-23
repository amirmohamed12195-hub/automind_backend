<?php

namespace App\Contracts;

interface GeocodingProvider
{
    public function geocode(string $address, string $countryCode): array;
}
