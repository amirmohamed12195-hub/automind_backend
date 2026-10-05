<?php

namespace App\Services\Diagnostics;

use App\Models\DiagnosticSession;
use App\Support\ContentLocale;

class DiagnosticManifestBuilder
{
    public function build(DiagnosticSession $session): array
    {
        $session->loadMissing(['vehicle', 'symptoms', 'media', 'obdSnapshots.troubleCodes', 'vehicle.maintenanceRecords']);

        return [
            'trustedMetadata' => ['sessionId' => (string) $session->id, 'inputLocale' => app(ContentLocale::class)->resolve($session->description, $session->input_locale, [$session->vehicle->brand, $session->vehicle->model]), 'reportLocale' => app(ContentLocale::class)->supported($session->report_locale), 'market' => ['countryCode' => $session->market_country_code, 'city' => $session->market_city, 'currency' => $session->market_currency]],
            'vehicle' => ['brand' => $session->vehicle->brand, 'model' => $session->vehicle->model, 'year' => (int) $session->vehicle->year, 'engine' => $session->vehicle->engine, 'fuelType' => $session->vehicle->fuel_type, 'transmission' => $session->vehicle->transmission, 'mileageKm' => (int) $session->vehicle->mileage_km, 'vinPresent' => $session->vehicle->vin !== null],
            'untrustedEvidence' => [
                'description' => $session->description, 'selectedSymptoms' => $session->symptoms->pluck('code')->values()->all(),
                'obdSnapshots' => $session->obdSnapshots->map(fn ($snapshot) => ['recordedAt' => $snapshot->recorded_at?->utc()->toIso8601ZuluString(), 'rpm' => $snapshot->rpm, 'speedKmh' => $snapshot->speed_kmh, 'coolantCelsius' => $snapshot->coolant_celsius, 'batteryVolts' => $snapshot->battery_volts, 'fuelTrimPercent' => $snapshot->short_fuel_trim_percent, 'engineLoadPercent' => $snapshot->engine_load_percent, 'troubleCodes' => $snapshot->troubleCodes->map(fn ($code) => ['code' => $code->code, 'description' => $code->raw_description, 'status' => $code->status])->all()])->all(),
                'spokenDescription' => null, 'photoObservations' => [], 'engineSoundObservations' => [],
            ],
            'evidenceInventory' => ['photos' => $session->media->where('media_kind', 'photo')->whereNull('deleted_at')->count(), 'engineSound' => $session->media->where('media_kind', 'engine_sound')->whereNull('deleted_at')->isNotEmpty(), 'spokenDescription' => $session->media->where('media_kind', 'spoken_description')->whereNull('deleted_at')->isNotEmpty(), 'obd' => $session->obdSnapshots->isNotEmpty(), 'serviceHistory' => $session->vehicle->maintenanceRecords->isNotEmpty()],
        ];
    }
}
