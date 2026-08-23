<?php

namespace App\Services\Diagnostics;

use App\Models\DiagnosticReport;
use App\Models\DiagnosticSession;
use App\Services\Maintenance\ReportMaintenanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiagnosticReportPersister
{
    private const CANONICAL_CODE_MAX_LENGTH = 120;

    public function __construct(private readonly ReportMaintenanceService $maintenance) {}

    public function persist(DiagnosticSession $session, array $data): DiagnosticReport
    {
        return DB::transaction(function () use ($session, $data) {
            $report = DiagnosticReport::query()->create([
                'diagnostic_session_id' => $session->id, 'user_id' => $session->user_id, 'vehicle_id' => $session->vehicle_id,
                'overall_confidence' => $data['overallConfidence'], 'severity' => $data['severity'], 'driving_recommendation' => $data['drivingRecommendation'],
                'evidence_quality' => $data['evidenceQuality'], 'professional_inspection_required' => $data['professionalInspectionRequired'],
                'prompt_version' => config('automind.diagnostic_prompt_version'), 'schema_version' => config('automind.diagnostic_schema_version'),
                'generated_at' => now(), 'verified_at' => now(), 'disclaimer_version' => config('automind.disclaimer_version'),
                'limitations' => $data['limitations'], 'missing_evidence' => $data['missingEvidence'],
            ]);
            foreach (['en', 'ar'] as $locale) {
                $report->translations()->create(['locale' => $locale, 'title' => $data['title'][$locale], 'summary' => $data['summary'][$locale], 'driving_advice' => $data['drivingAdvice'][$locale], 'disclaimer' => config("automind.disclaimer.$locale")]);
            }

            foreach ($data['suspectedFaults'] as $faultIndex => $faultData) {
                $fault = $report->faults()->create(['canonical_fault_code' => $faultData['canonicalCode'], 'obd_code' => $faultData['obdCode'], 'confidence' => $faultData['confidence'], 'severity' => $faultData['severity'], 'sort_order' => $faultIndex]);
                foreach (['en', 'ar'] as $locale) {
                    $fault->translations()->create(['locale' => $locale, 'title' => $faultData['title'][$locale], 'description' => $faultData['description'][$locale]]);
                }
                foreach ($faultData['possibleCauses'] as $causeIndex => $causeData) {
                    $cause = $fault->causes()->create(['canonical_code' => $this->canonicalCauseCode($causeData['en']), 'sort_order' => $causeIndex]);
                    foreach (['en', 'ar'] as $locale) {
                        $cause->translations()->create(['locale' => $locale, 'text' => $causeData[$locale]]);
                    }
                }
                foreach ($faultData['recommendedActions'] as $actionIndex => $actionData) {
                    $this->action($report, $actionData, 'recommended_action', $actionIndex, $fault->id);
                }
                foreach ($faultData['recommendedParts'] as $partIndex => $partData) {
                    $part = $fault->parts()->create(['diagnostic_report_id' => $report->id, 'canonical_part_name' => $partData['canonicalName'], 'part_number' => $partData['partNumber'], 'vehicle_compatibility_text' => $partData['searchKeywords']['en'], 'compatibility_confidence' => $partData['compatibilityConfidence'], 'required' => $partData['required'], 'sort_order' => $partIndex]);
                    foreach (['en', 'ar'] as $locale) {
                        $part->translations()->create(['locale' => $locale, 'display_name' => $partData['name'][$locale], 'reason' => $partData['reason'][$locale]]);
                    }
                }
                foreach ($faultData['evidence'] as $evidence) {
                    $fault->evidence()->create(['diagnostic_report_id' => $report->id, 'source_type' => $this->sourceType($evidence['sourceType']), 'source_record_id' => $this->ulidOrNull($evidence['referenceId']), 'reliability' => $evidence['reliability'], 'observation_en' => $evidence['observation']['en'], 'observation_ar' => $evidence['observation']['ar']]);
                }
            }
            foreach ($data['safeChecks'] as $index => $action) {
                $this->action($report, ['code' => 'safe_check_'.($index + 1), 'text' => $action['text'], 'priority' => 3, 'professionalRequired' => false, 'stopCondition' => $action['stopCondition']], 'safe_check', $index);
            }
            foreach ($data['recommendedActions'] as $index => $action) {
                $this->action($report, $action, 'recommended_action', $index);
            }
            foreach ($data['emergencyWarnings'] as $index => $warning) {
                $this->action($report, ['code' => 'emergency_warning_'.($index + 1), 'text' => $warning, 'priority' => 1, 'professionalRequired' => true], 'emergency_warning', $index);
            }

            $this->maintenance->syncRecommendations($report);

            return $report;
        });
    }

    private function action(DiagnosticReport $report, array $data, string $type, int $order, ?string $faultId = null): void
    {
        $action = $report->actions()->create(['suspected_fault_id' => $faultId, 'action_type' => $type, 'canonical_code' => $data['code'] ?? null, 'priority' => $data['priority'] ?? 3, 'professional_required' => $data['professionalRequired'] ?? false, 'stop_condition_code' => isset($data['stopCondition']) ? 'stop_if_unsafe' : null, 'sort_order' => $order]);
        foreach (['en', 'ar'] as $locale) {
            $action->translations()->create(['locale' => $locale, 'text' => $data['text'][$locale], 'stop_condition_text' => $data['stopCondition'][$locale] ?? null]);
        }
    }

    private function sourceType(string $source): string
    {
        return match ($source) {
            'engineSound' => 'engine_sound', 'spokenDescription' => 'spoken_description', default => $source
        };
    }

    private function ulidOrNull(?string $value): ?string
    {
        return $value && Str::isUlid($value) ? $value : null;
    }

    private function canonicalCauseCode(string $value): string
    {
        $slug = Str::slug($value, '_');
        $hash = substr(hash('sha256', $slug !== '' ? $slug : $value), 0, 12);

        if ($slug === '') {
            return 'cause_'.$hash;
        }

        if (strlen($slug) <= self::CANONICAL_CODE_MAX_LENGTH) {
            return $slug;
        }

        $prefixLength = self::CANONICAL_CODE_MAX_LENGTH - strlen($hash) - 1;
        $prefix = rtrim(substr($slug, 0, $prefixLength), '_');

        return $prefix.'_'.$hash;
    }
}
