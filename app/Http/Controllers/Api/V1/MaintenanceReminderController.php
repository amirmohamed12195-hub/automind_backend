<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MaintenanceReminderResource;
use App\Models\MaintenanceReminder;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MaintenanceReminderController
{
    public function index(Vehicle $vehicle)
    {
        Gate::authorize('view', $vehicle);

        return ApiResponse::success($vehicle->reminders()->get()->map(fn ($r) => MaintenanceReminderResource::make($r, $vehicle))->all());
    }

    public function store(Request $request, Vehicle $vehicle)
    {
        Gate::authorize('update', $vehicle);
        $data = $this->validate($request, true);
        $reminder = $vehicle->reminders()->create(['service_definition_id' => $data['serviceDefinitionId'], 'due_date' => $data['dueDate'] ?? null, 'due_km' => $data['dueKm'] ?? null, 'notification_preferences' => $data['notificationPreferences'] ?? null]);

        return ApiResponse::success(MaintenanceReminderResource::make($reminder, $vehicle), 201);
    }

    public function update(Request $request, Vehicle $vehicle, MaintenanceReminder $reminder)
    {
        Gate::authorize('update', $vehicle);
        $this->nested($vehicle, $reminder);
        $data = $this->validate($request, false);
        $dueDate = array_key_exists('dueDate', $data) ? $data['dueDate'] : $reminder->due_date;
        $dueKm = array_key_exists('dueKm', $data) ? $data['dueKm'] : $reminder->due_km;
        if ($dueDate === null && $dueKm === null) {
            throw ValidationException::withMessages(['dueDate' => [__('api.reminder_due_required')]]);
        }
        $map = ['serviceDefinitionId' => 'service_definition_id', 'dueDate' => 'due_date', 'dueKm' => 'due_km', 'status' => 'status', 'notificationPreferences' => 'notification_preferences'];
        $updates = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $updates[$column] = $data[$key];
            }
        } $reminder->update($updates);

        return ApiResponse::success(MaintenanceReminderResource::make($reminder->fresh(), $vehicle));
    }

    public function complete(Request $request, Vehicle $vehicle, MaintenanceReminder $reminder)
    {
        Gate::authorize('update', $vehicle);
        $this->nested($vehicle, $reminder);
        if (! in_array($reminder->status, ['pending', 'snoozed'], true)) {
            return ApiResponse::error('REMINDER_IMMUTABLE', __('api.reminder_immutable'), 409);
        }
        $data = $request->validate(['serviceDate' => ['required', 'date', 'before_or_equal:today'], 'odometerKm' => ['required', 'integer', 'between:0,5000000'], 'amount' => ['nullable', 'decimal:0,2', 'min:0', 'required_with:currency'], 'currency' => ['nullable', 'string', 'size:3', 'alpha', 'required_with:amount'], 'notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($vehicle, $reminder, $data): void {
            $record = $vehicle->maintenanceRecords()->create(['service_definition_id' => $reminder->service_definition_id, 'service_date' => $data['serviceDate'], 'odometer_km' => $data['odometerKm'], 'amount' => $data['amount'] ?? null, 'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null, 'notes' => $data['notes'] ?? null]);
            $reminder->update(['status' => 'completed', 'completed_record_id' => $record->id]);
        });

        return ApiResponse::success(MaintenanceReminderResource::make($reminder->fresh(), $vehicle));
    }

    public function snooze(Request $request, Vehicle $vehicle, MaintenanceReminder $reminder)
    {
        Gate::authorize('update', $vehicle);
        $this->nested($vehicle, $reminder);
        if ($reminder->status !== 'pending') {
            return ApiResponse::error('REMINDER_IMMUTABLE', __('api.reminder_immutable'), 409);
        }
        $data = $request->validate(['until' => ['required', 'date', 'after:now']]);
        $reminder->update(['status' => 'snoozed', 'snoozed_until' => $data['until']]);

        return ApiResponse::success(MaintenanceReminderResource::make($reminder->fresh(), $vehicle));
    }

    private function validate(Request $request, bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        return $request->validate(['serviceDefinitionId' => [$required, 'ulid', 'exists:maintenance_service_definitions,id'], 'dueDate' => [$create ? 'required_without:dueKm' : 'sometimes', 'nullable', 'date'], 'dueKm' => [$create ? 'required_without:dueDate' : 'sometimes', 'nullable', 'integer', 'between:0,5000000'], 'status' => ['sometimes', 'in:pending,dismissed'], 'notificationPreferences' => ['sometimes', 'nullable', 'array']]);
    }

    private function nested(Vehicle $vehicle, MaintenanceReminder $reminder): void
    {
        if ($reminder->vehicle_id !== $vehicle->id) {
            abort(404);
        }
    }
}
