<?php

namespace App\Services\Diagnostics;

class DiagnosticSafetyPolicy
{
    private const CRITICAL = '/\b(fuel leak|gasoline leak|fire|flames?|brake (failure|loss)|no brakes|steering loss|cannot steer|severe overheating|temperature.*red|high[ -]?voltage.*exposed|دخان مشتعل|حريق|تسرب وقود|فقدان الفرامل|تعطل الفرامل|فقدان التوجيه|ارتفاع شديد.*الحرارة|جهد عالٍ مكشوف)\b/ui';

    private const UNSAFE = '/(open (the )?(hot )?radiator|touch (the )?(moving|hot|exposed)|go under|unsupported (car|vehicle)|handle (a )?fuel leak|bypass (a )?safety|continue driving.*critical|افتح.*المبرد.*ساخن|المس.*متحرك|انزل تحت.*دون تثبيت|تعامل.*تسرب الوقود|تجاوز.*السلامة)/ui';

    public function enforce(array $report, array $evidenceManifest): array
    {
        $evidence = json_encode($evidenceManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (preg_match(self::CRITICAL, $evidence) === 1) {
            $report['severity'] = 'critical';
            if (! in_array($report['drivingRecommendation'], ['stopImmediately', 'towRequired'], true)) {
                $report['drivingRecommendation'] = 'stopImmediately';
            }
            $report['professionalInspectionRequired'] = true;
            $warning = ['en' => 'Stop using the vehicle immediately and arrange professional assistance.', 'ar' => 'أوقف استخدام المركبة فوراً واطلب مساعدة مهنية.'];
            if (! in_array($warning, $report['emergencyWarnings'], true)) {
                array_unshift($report['emergencyWarnings'], $warning);
            }
        }

        $manifestWithoutAudio = $evidenceManifest;
        unset($manifestWithoutAudio['untrustedEvidence']['engineSoundObservations']);
        $nonAudioCriticalSignal = preg_match(self::CRITICAL, json_encode($manifestWithoutAudio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === 1;
        foreach ($report['suspectedFaults'] as &$fault) {
            $sourceTypes = collect($fault['evidence'] ?? [])->pluck('sourceType')->filter()->unique()->values()->all();
            if (($fault['severity'] ?? null) === 'critical' && $sourceTypes !== [] && collect($sourceTypes)->every(fn ($type) => $type === 'engineSound')) {
                $fault['severity'] = 'high';
                $fault['confidence'] = min((float) ($fault['confidence'] ?? 0), 0.45);
            }
        }
        unset($fault);
        if (($report['severity'] ?? null) === 'critical' && ! $nonAudioCriticalSignal && ! collect($report['suspectedFaults'])->contains(fn ($fault) => ($fault['severity'] ?? null) === 'critical')) {
            $report['severity'] = 'high';
            $report['overallConfidence'] = min((float) $report['overallConfidence'], 0.45);
            $report['professionalInspectionRequired'] = true;
        }

        $quarantined = [];
        foreach (['safeChecks', 'recommendedActions'] as $key) {
            $report[$key] = array_values(array_filter($report[$key], function ($action) use (&$quarantined) {
                if (preg_match(self::UNSAFE, json_encode($action, JSON_UNESCAPED_UNICODE)) === 1) {
                    $quarantined[] = $action;

                    return false;
                }

                return true;
            }));
        }
        if (($report['evidenceQuality'] ?? null) === 'poor' || count($report['missingEvidence'] ?? []) >= 4) {
            $report['overallConfidence'] = min((float) $report['overallConfidence'], 0.55);
            $report['professionalInspectionRequired'] = true;
        }
        $report['_safety'] = ['quarantinedActions' => $quarantined];

        return $report;
    }
}
