<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MaintenanceReminderResource;
use App\Models\DiagnosticReport;
use App\Models\ReportAction;
use App\Services\Maintenance\ReportMaintenanceService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportMaintenanceController
{
    public function store(
        Request $request,
        DiagnosticReport $report,
        ReportMaintenanceService $maintenance,
    ) {
        Gate::authorize('update', $report);
        $data = $request->validate([
            'actionIds' => ['required', 'array', 'between:1,10'],
            'actionIds.*' => ['required', 'ulid', 'distinct'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:today'],
            'dueKm' => ['nullable', 'integer', 'between:0,5000000'],
        ]);
        $actions = ReportAction::query()
            ->where('diagnostic_report_id', $report->id)
            ->where('action_type', 'recommended_action')
            ->whereIn('id', $data['actionIds'])
            ->with('translations')
            ->get();
        if ($actions->count() !== count($data['actionIds'])) {
            throw ValidationException::withMessages(['actionIds' => [__('api.report_actions_invalid')]]);
        }
        $reminders = $maintenance->syncActions(
            $report,
            $actions,
            $data['dueDate'] ?? null,
            $data['dueKm'] ?? null,
        );

        return ApiResponse::success(
            $reminders->map(fn ($reminder) => MaintenanceReminderResource::make($reminder, $report->vehicle))->all(),
            201,
        );
    }
}
