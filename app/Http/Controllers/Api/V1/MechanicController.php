<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MechanicResource;
use App\Models\Mechanic;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MechanicController
{
    public function index(Request $request)
    {
        $data = $request->validate(['latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'], 'radiusKm' => ['nullable', 'numeric', 'between:1,200'], 'specialty' => ['nullable', 'string', 'max:100']]);
        $query = Mechanic::query()->where('active', true)->where('verified', true)->with('specialties');
        if (! empty($data['specialty'])) {
            $query->whereHas('specialties', fn ($q) => $q->where('code', $data['specialty']));
        }
        $items = $query->get();
        if (isset($data['latitude'], $data['longitude'])) {
            $lat = (float) $data['latitude'];
            $lon = (float) $data['longitude'];
            $radius = (float) ($data['radiusKm'] ?? 50);
            $items = $items->map(function ($m) use ($lat, $lon) {
                $m->distance_km = $this->distance($lat, $lon, (float) $m->latitude, (float) $m->longitude);

                return $m;
            })->filter(fn ($m) => $m->distance_km <= $radius)->sortBy('distance_km')->values();
        }

        return ApiResponse::success(MechanicResource::collection($items)->resolve());
    }

    public function show(Mechanic $mechanic)
    {
        abort_unless($mechanic->active && $mechanic->verified, 404);

        return ApiResponse::success((new MechanicResource($mechanic->load('specialties')))->resolve());
    }

    public function availability(Request $request, Mechanic $mechanic)
    {
        abort_unless($mechanic->active && $mechanic->verified, 404);
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from']]);
        $from = CarbonImmutable::parse($data['from']);
        $to = CarbonImmutable::parse($data['to']);
        if ($from->diffInDays($to) > 31) {
            throw ValidationException::withMessages(['to' => [__('api.availability_range')]]);
        }
        $busy = $mechanic->appointments()->whereIn('status', ['requested', 'confirmed'])->where('requested_start_at', '<', $to)->where('requested_end_at', '>', $from)->orderBy('requested_start_at')->get();

        return ApiResponse::success(['mechanicId' => (string) $mechanic->id, 'from' => $from->utc()->toIso8601ZuluString(), 'to' => $to->utc()->toIso8601ZuluString(), 'workingHours' => $mechanic->working_hours_json, 'busy' => $busy->map(fn ($a) => ['start' => $a->requested_start_at->utc()->toIso8601ZuluString(), 'end' => $a->requested_end_at->utc()->toIso8601ZuluString()])->all()]);
    }

    private function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
