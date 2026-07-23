<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\MaintenanceRecordRequest;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Gate;

class MaintenanceController
{
    public function index(Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);
        $records = $vehicle->maintenanceRecords()->latest('service_date')->get()->map(fn ($r) => $this->resource($r))->all();

        return ApiResponse::success($records);
    }

    public function store(MaintenanceRecordRequest $request, Vehicle $vehicle)
    {
        Gate::authorize('update', $vehicle);
        $record = $vehicle->maintenanceRecords()->create($this->attributes($request));

        return ApiResponse::success($this->resource($record), 201);
    }

    public function show(Vehicle $vehicle, VehicleMaintenanceRecord $record)
    {
        Gate::authorize('view', $vehicle);
        $this->nested($vehicle, $record);

        return ApiResponse::success($this->resource($record));
    }

    public function update(MaintenanceRecordRequest $request, Vehicle $vehicle, VehicleMaintenanceRecord $record)
    {
        Gate::authorize('update', $vehicle);
        $this->nested($vehicle, $record);
        $record->update($this->attributes($request));

        return ApiResponse::success($this->resource($record->fresh()));
    }

    public function destroy(Vehicle $vehicle, VehicleMaintenanceRecord $record)
    {
        Gate::authorize('update', $vehicle);
        $this->nested($vehicle, $record);
        $record->delete();

        return response()->noContent();
    }

    private function nested(Vehicle $vehicle, VehicleMaintenanceRecord $record): void
    {
        if ($record->vehicle_id !== $vehicle->id) {
            abort(404);
        }
    }

    private function attributes(MaintenanceRecordRequest $request): array
    {
        $map = ['serviceDefinitionId' => 'service_definition_id', 'customService' => 'custom_service', 'serviceDate' => 'service_date', 'odometerKm' => 'odometer_km', 'amount' => 'amount', 'currency' => 'currency', 'mechanic' => 'mechanic', 'notes' => 'notes', 'nextDueDate' => 'next_due_date', 'nextDueKm' => 'next_due_km'];
        $out = [];
        foreach ($map as $key => $column) {
            if ($request->exists($key)) {
                $out[$column] = $key === 'currency' && $request->input($key) ? strtoupper($request->input($key)) : $request->input($key);
            }
        }

        return $out;
    }

    private function resource(VehicleMaintenanceRecord $r): array
    {
        return ['id' => (string) $r->id, 'vehicleId' => (string) $r->vehicle_id, 'serviceDefinitionId' => $r->service_definition_id, 'customService' => $r->custom_service, 'serviceDate' => $r->service_date?->toDateString(), 'odometerKm' => (int) $r->odometer_km, 'amount' => $r->amount, 'currency' => $r->currency, 'mechanic' => $r->mechanic, 'notes' => $r->notes, 'nextDueDate' => $r->next_due_date?->toDateString(), 'nextDueKm' => $r->next_due_km];
    }
}
