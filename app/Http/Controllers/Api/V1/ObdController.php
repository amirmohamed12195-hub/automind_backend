<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ObdSnapshotRequest;
use App\Models\DiagnosticSession;
use App\Models\ObdSnapshot;
use App\Services\Diagnostics\ObdNormalizer;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ObdController
{
    public function store(ObdSnapshotRequest $request, DiagnosticSession $diagnosis, ObdNormalizer $normalizer)
    {
        Gate::authorize('update', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'uploading'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $normalized = $normalizer->normalize($request->validated());
        $codes = $normalized['trouble_codes'];
        unset($normalized['trouble_codes']);
        $snapshot = DB::transaction(function () use ($diagnosis, $normalized, $codes) {
            $snapshot = $diagnosis->obdSnapshots()->create($normalized);
            foreach ($codes as $code) {
                $snapshot->troubleCodes()->create(['code' => $code, 'status' => 'unknown']);
            }

            return $snapshot;
        });

        return ApiResponse::success($this->resource($snapshot->load('troubleCodes')), 201);
    }

    private function resource(ObdSnapshot $s): array
    {
        return ['id' => (string) $s->id, 'recordedAt' => $s->recorded_at?->utc()->toIso8601ZuluString(), 'troubleCodes' => $s->troubleCodes->pluck('code')->all(), 'rpm' => $s->rpm !== null ? (float) $s->rpm : null, 'speedKmh' => $s->speed_kmh !== null ? (float) $s->speed_kmh : null, 'coolantCelsius' => $s->coolant_celsius !== null ? (float) $s->coolant_celsius : null, 'batteryVoltage' => $s->battery_volts !== null ? (float) $s->battery_volts : null, 'fuelTrim' => $s->short_fuel_trim_percent !== null ? (float) $s->short_fuel_trim_percent : null, 'engineLoad' => $s->engine_load_percent !== null ? (float) $s->engine_load_percent : null];
    }
}
