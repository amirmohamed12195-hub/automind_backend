<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingException;
use App\Http\Requests\VehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\Billing\EntitlementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VehicleController
{
    public function index(Request $request)
    {
        return ApiResponse::success(VehicleResource::collection($request->user()->vehicles()->latest('updated_at')->get())->resolve());
    }

    public function store(VehicleRequest $request, EntitlementService $entitlements)
    {
        $this->validateCatalogPair($request);
        if (config('billing.enabled')) {
            $limit = $entitlements->snapshot($request->user())['limits']['maxVehicles'];
            if (is_int($limit) && $request->user()->vehicles()->count() >= $limit) {
                throw new BillingException('VEHICLE_LIMIT_REACHED', 'Your current plan vehicle limit has been reached.', 402);
            }
        }
        if ($request->filled('vin') && Vehicle::query()->where('user_id', $request->user()->id)->where('vin', strtoupper($request->input('vin')))->exists()) {
            return ApiResponse::error('VIN_ALREADY_EXISTS', __('api.validation_failed'), 422, ['vin' => [__('api.vin_exists')]]);
        }
        $vehicle = $request->user()->vehicles()->create($this->attributes($request));
        if (! DB::table('user_selected_vehicles')->where('user_id', $request->user()->id)->exists()) {
            DB::table('user_selected_vehicles')->insert(['user_id' => $request->user()->id, 'vehicle_id' => $vehicle->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ApiResponse::success((new VehicleResource($vehicle))->resolve(), 201);
    }

    public function show(Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);

        return ApiResponse::success((new VehicleResource($vehicle))->resolve());
    }

    public function update(VehicleRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('update', $vehicle);
        $this->validateCatalogPair($request, $vehicle);
        if ($request->filled('vin') && Vehicle::query()->where('user_id', $request->user()->id)->where('vin', strtoupper($request->input('vin')))->whereKeyNot($vehicle->id)->exists()) {
            return ApiResponse::error('VIN_ALREADY_EXISTS', __('api.validation_failed'), 422, ['vin' => [__('api.vin_exists')]]);
        }
        $vehicle->update($this->attributes($request));

        return ApiResponse::success((new VehicleResource($vehicle->fresh()))->resolve());
    }

    public function destroy(Vehicle $vehicle)
    {
        Gate::authorize('delete', $vehicle);
        $vehicle->delete();

        return response()->noContent();
    }

    public function select(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);
        DB::table('user_selected_vehicles')->updateOrInsert(['user_id' => $request->user()->id], ['vehicle_id' => $vehicle->id, 'created_at' => now(), 'updated_at' => now()]);

        return ApiResponse::success((new VehicleResource($vehicle))->resolve());
    }

    public function health(Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);
        $due = $vehicle->reminders()->where('status', 'pending')->where(function ($q) use ($vehicle) {
            $q->whereDate('due_date', '<=', today())->orWhere('due_km', '<=', $vehicle->mileage_km);
        })->count();

        return ApiResponse::success(['vehicleId' => (string) $vehicle->id, 'healthScore' => (int) $vehicle->health_score, 'overdueMaintenanceCount' => $due, 'lastUpdatedAt' => $vehicle->updated_at?->utc()->toIso8601ZuluString()]);
    }

    private function attributes(Request $request): array
    {
        $map = ['brand' => 'brand', 'model' => 'model', 'year' => 'year', 'engine' => 'engine', 'fuelType' => 'fuel_type', 'transmission' => 'transmission', 'mileage' => 'mileage_km', 'vin' => 'vin', 'plateNumber' => 'plate_number', 'nickname' => 'nickname', 'catalogMakeId' => 'catalog_make_id', 'catalogModelId' => 'catalog_model_id'];
        $result = [];
        foreach ($map as $external => $column) {
            if ($request->exists($external)) {
                $result[$column] = $external === 'vin' && $request->input($external) ? strtoupper($request->input($external)) : $request->input($external);
            }
        }

        return $result;
    }

    private function validateCatalogPair(Request $request, ?Vehicle $vehicle = null): void
    {
        $makeId = $request->exists('catalogMakeId') ? $request->input('catalogMakeId') : $vehicle?->catalog_make_id;
        $modelId = $request->exists('catalogModelId') ? $request->input('catalogModelId') : $vehicle?->catalog_model_id;
        if (! $makeId || ! $modelId) {
            return;
        }

        $matches = DB::table('vehicle_models')
            ->where('id', $modelId)
            ->where('make_id', $makeId)
            ->exists();
        if (! $matches) {
            throw ValidationException::withMessages([
                'catalogModelId' => [__('api.catalog_model_make_mismatch')],
            ]);
        }
    }
}
