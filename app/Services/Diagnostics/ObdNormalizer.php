<?php

namespace App\Services\Diagnostics;

class ObdNormalizer
{
    public function normalize(array $input): array
    {
        $speed = isset($input['speed']) ? (float) $input['speed'] : null;
        if ($speed !== null && data_get($input, 'units.speed') === 'mph') {
            $speed *= 1.609344;
        }
        $coolant = isset($input['coolantTemperature']) ? (float) $input['coolantTemperature'] : null;
        if ($coolant !== null && data_get($input, 'units.coolantTemperature') === 'fahrenheit') {
            $coolant = ($coolant - 32) * 5 / 9;
        }

        return [
            'recorded_at' => $input['recordedAt'], 'rpm' => $input['rpm'] ?? null, 'speed_kmh' => $speed,
            'coolant_celsius' => $coolant, 'battery_volts' => $input['batteryVoltage'] ?? null,
            'short_fuel_trim_percent' => $input['fuelTrim'] ?? null, 'long_fuel_trim_percent' => null,
            'engine_load_percent' => $input['engineLoad'] ?? null, 'raw_json' => $input,
            'trouble_codes' => array_values(array_unique(array_map('strtoupper', $input['troubleCodes'] ?? []))),
        ];
    }
}
