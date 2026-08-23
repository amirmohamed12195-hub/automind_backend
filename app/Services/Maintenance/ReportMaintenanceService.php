<?php

namespace App\Services\Maintenance;

use App\Models\DiagnosticReport;
use App\Models\MaintenanceReminder;
use App\Models\MaintenanceServiceDefinition;
use App\Models\ReportAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportMaintenanceService
{
    /** @return Collection<int, MaintenanceReminder> */
    public function syncRecommendations(DiagnosticReport $report): Collection
    {
        $actions = $report->actions()
            ->where('action_type', 'recommended_action')
            ->whereNull('suspected_fault_id')
            ->with('translations')
            ->get();
        if ($actions->isEmpty()) {
            $actions = $report->actions()
                ->where('action_type', 'recommended_action')
                ->with('translations')
                ->get();
        }

        return $this->syncActions($report, $actions);
    }

    /**
     * @param  Collection<int, ReportAction>  $actions
     * @return Collection<int, MaintenanceReminder>
     */
    public function syncActions(
        DiagnosticReport $report,
        Collection $actions,
        ?string $dueDate = null,
        ?int $dueKm = null,
    ): Collection {
        $report->loadMissing('vehicle');
        $fallback = MaintenanceServiceDefinition::query()->firstOrCreate(
            ['code' => 'diagnostic_follow_up'],
            [
                'name_en' => 'Diagnostic follow-up', 'name_ar' => 'متابعة التشخيص',
                'description_en' => 'A task created from an AutoMind diagnostic report.',
                'description_ar' => 'مهمة أُنشئت من تقرير تشخيص أوتومايند.',
                'active' => true,
            ],
        );
        $resolvedDueDate = $dueDate === null
            ? now()->addDays(7)->toDateString()
            : CarbonImmutable::parse($dueDate)->toDateString();

        return $actions->map(function (ReportAction $action) use ($report, $fallback, $resolvedDueDate, $dueKm) {
            $en = $action->translations->firstWhere('locale', 'en')?->text;
            $ar = $action->translations->firstWhere('locale', 'ar')?->text;
            $service = MaintenanceServiceDefinition::query()
                ->where('code', $action->canonical_code)->where('active', true)->first()
                ?? $fallback;

            return $report->vehicle->reminders()->firstOrCreate(
                ['source_report_action_id' => $action->id],
                [
                    'service_definition_id' => $service->id,
                    'source_report_id' => $report->id,
                    'source_action_text_en' => $en,
                    'source_action_text_ar' => $ar,
                    'due_date' => $resolvedDueDate,
                    'due_km' => $dueKm,
                    'notification_preferences' => ['daysBefore' => [7, 1]],
                ],
            );
        })->values();
    }
}
