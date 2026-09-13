<?php

namespace App\Services\Diagnostics;

use Illuminate\Support\Str;

class DiagnosticReportReconciler
{
    public function reconcile(array $report, array $manifest): array
    {
        $availability = $this->availability($manifest);
        $report['missingEvidence'] = array_values(array_filter(
            $report['missingEvidence'] ?? [],
            fn (string $type): bool => ! ($availability[$type] ?? false),
        ));

        $report['recommendedActions'] = $this->uniqueActions($report['recommendedActions'] ?? []);
        foreach ($report['suspectedFaults'] as &$fault) {
            $fault['recommendedActions'] = $this->uniqueActions($fault['recommendedActions'] ?? []);
            $fault['evidence'] = $this->reconcileEvidence($fault['evidence'] ?? [], $availability, $manifest);
        }
        unset($fault);

        return $report;
    }

    private function availability(array $manifest): array
    {
        $inventory = $manifest['evidenceInventory'] ?? [];
        $evidence = $manifest['untrustedEvidence'] ?? [];
        $vehicle = $manifest['vehicle'] ?? [];

        return [
            'description' => trim((string) ($evidence['description'] ?? '')) !== '',
            'symptoms' => ! empty($evidence['selectedSymptoms']),
            'engineSound' => (bool) ($inventory['engineSound'] ?? false) || ! empty($evidence['engineSoundObservations']),
            'photos' => (int) ($inventory['photos'] ?? 0) > 0 || ! empty($evidence['photoObservations']),
            'obd' => (bool) ($inventory['obd'] ?? false) || ! empty($evidence['obdSnapshots']),
            'mileage' => array_key_exists('mileageKm', $vehicle) && $vehicle['mileageKm'] !== null,
            'vin' => (bool) ($vehicle['vinPresent'] ?? false),
            'serviceHistory' => (bool) ($inventory['serviceHistory'] ?? false),
            'spokenDescription' => (bool) ($inventory['spokenDescription'] ?? false) || ! empty($evidence['spokenDescription']),
        ];
    }

    private function reconcileEvidence(array $items, array $availability, array $manifest): array
    {
        $result = [];
        $seen = [];
        $photoIds = collect(data_get($manifest, 'untrustedEvidence.photoObservations.observations', []))->pluck('sourceMediaId')->filter()->unique()->values();
        $engineSoundId = data_get($manifest, 'untrustedEvidence.engineSoundObservations.sourceMediaId');
        $spokenDescriptionId = data_get($manifest, 'untrustedEvidence.spokenDescription.sourceMediaId');

        foreach ($items as $item) {
            $source = $item['sourceType'] ?? null;
            if ($source === 'spokenDescription' && ! $availability['spokenDescription']) {
                if (! $availability['description'] && ! $availability['symptoms']) {
                    continue;
                }
                $item['sourceType'] = 'text';
                $item['referenceId'] = null;
                $source = 'text';
            }

            $isAvailable = match ($source) {
                'text' => $availability['description'] || $availability['symptoms'],
                'photo' => $availability['photos'],
                'engineSound' => $availability['engineSound'],
                'spokenDescription' => $availability['spokenDescription'],
                'obd' => $availability['obd'],
                default => false,
            };
            if (! $isAvailable) {
                continue;
            }

            if ($source === 'photo' && $photoIds->count() === 1 && ! $photoIds->contains($item['referenceId'] ?? null)) {
                $item['referenceId'] = $photoIds->first();
            } elseif ($source === 'engineSound' && is_string($engineSoundId) && $engineSoundId !== '') {
                $item['referenceId'] = $engineSoundId;
            } elseif ($source === 'spokenDescription' && is_string($spokenDescriptionId) && $spokenDescriptionId !== '') {
                $item['referenceId'] = $spokenDescriptionId;
            }

            $signature = Str::lower(trim((string) $item['sourceType'])).'|'.($item['referenceId'] ?? '').'|'.Str::lower(trim((string) data_get($item, 'observation.en', '')));
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $result[] = $item;
        }

        return $result;
    }

    private function uniqueActions(array $actions): array
    {
        $unique = [];
        foreach ($actions as $action) {
            $key = Str::lower(trim((string) ($action['code'] ?? '')));
            if ($key === '') {
                $key = Str::lower(trim((string) data_get($action, 'text.en', '')));
            }
            if (! isset($unique[$key])) {
                $unique[$key] = $action;

                continue;
            }

            $unique[$key]['priority'] = min((int) $unique[$key]['priority'], (int) $action['priority']);
            $unique[$key]['professionalRequired'] = (bool) $unique[$key]['professionalRequired'] || (bool) $action['professionalRequired'];
        }

        return array_values($unique);
    }
}
