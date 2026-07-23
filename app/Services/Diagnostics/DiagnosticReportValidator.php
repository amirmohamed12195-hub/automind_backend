<?php

namespace App\Services\Diagnostics;

use Illuminate\Support\Facades\Validator;

class DiagnosticReportValidator
{
    public function validate(array $data): array
    {
        Validator::make($data, [
            'title.en' => ['required', 'string', 'max:240'], 'title.ar' => ['required', 'string', 'max:240'],
            'summary.en' => ['required', 'string'], 'summary.ar' => ['required', 'string'],
            'overallConfidence' => ['required', 'numeric', 'between:0,1'], 'severity' => ['required', 'in:low,medium,high,critical,unknown'],
            'drivingRecommendation' => ['required', 'in:safeToDrive,driveWithCaution,stopSoon,stopImmediately,towRequired,unknown'],
            'drivingAdvice.en' => ['required', 'string'], 'drivingAdvice.ar' => ['required', 'string'],
            'evidenceQuality' => ['required', 'in:poor,limited,moderate,strong'], 'professionalInspectionRequired' => ['required', 'boolean'],
            'emergencyWarnings' => ['present', 'array', 'max:8'], 'suspectedFaults' => ['required', 'array', 'between:1,5'],
            'safeChecks' => ['present', 'array', 'max:8'], 'recommendedActions' => ['required', 'array', 'between:1,10'],
            'limitations' => ['required', 'array', 'between:1,8'], 'missingEvidence' => ['present', 'array', 'max:8'],
            'emergencyWarnings.*.en' => ['required', 'string'], 'emergencyWarnings.*.ar' => ['required', 'string'],
            'limitations.*.en' => ['required', 'string'], 'limitations.*.ar' => ['required', 'string'],
            'missingEvidence.*' => ['distinct', 'in:description,symptoms,engineSound,photos,obd,mileage,vin,serviceHistory'],
            'suspectedFaults.*.canonicalCode' => ['required', 'string', 'max:120'], 'suspectedFaults.*.obdCode' => ['present', 'nullable', 'regex:/^[PBCU][0-3A-F][0-9A-F]{3}$/i'],
            'suspectedFaults.*.title.en' => ['required', 'string'], 'suspectedFaults.*.title.ar' => ['required', 'string'],
            'suspectedFaults.*.description.en' => ['required', 'string'], 'suspectedFaults.*.description.ar' => ['required', 'string'],
            'suspectedFaults.*.confidence' => ['required', 'numeric', 'between:0,1'], 'suspectedFaults.*.severity' => ['required', 'in:low,medium,high,critical,unknown'],
            'suspectedFaults.*.evidence' => ['present', 'array', 'max:12'], 'suspectedFaults.*.possibleCauses' => ['present', 'array', 'max:8'],
            'suspectedFaults.*.recommendedActions' => ['present', 'array', 'max:8'], 'suspectedFaults.*.recommendedParts' => ['present', 'array', 'max:8'],
            'suspectedFaults.*.evidence.*.sourceType' => ['required', 'in:text,photo,engineSound,spokenDescription,obd'],
            'suspectedFaults.*.evidence.*.referenceId' => ['present', 'nullable', 'string'],
            'suspectedFaults.*.evidence.*.observation.en' => ['required', 'string'], 'suspectedFaults.*.evidence.*.observation.ar' => ['required', 'string'],
            'suspectedFaults.*.evidence.*.reliability' => ['required', 'numeric', 'between:0,1'],
            'suspectedFaults.*.possibleCauses.*.en' => ['required', 'string'], 'suspectedFaults.*.possibleCauses.*.ar' => ['required', 'string'],
            'suspectedFaults.*.recommendedActions.*.code' => ['required', 'string'],
            'suspectedFaults.*.recommendedActions.*.text.en' => ['required', 'string'], 'suspectedFaults.*.recommendedActions.*.text.ar' => ['required', 'string'],
            'suspectedFaults.*.recommendedActions.*.priority' => ['required', 'integer', 'between:1,5'], 'suspectedFaults.*.recommendedActions.*.professionalRequired' => ['required', 'boolean'],
            'suspectedFaults.*.recommendedParts.*.canonicalName' => ['required', 'string'],
            'suspectedFaults.*.recommendedParts.*.name.en' => ['required', 'string'], 'suspectedFaults.*.recommendedParts.*.name.ar' => ['required', 'string'],
            'suspectedFaults.*.recommendedParts.*.reason.en' => ['required', 'string'], 'suspectedFaults.*.recommendedParts.*.reason.ar' => ['required', 'string'],
            'suspectedFaults.*.recommendedParts.*.partNumber' => ['present', 'nullable', 'string'], 'suspectedFaults.*.recommendedParts.*.required' => ['required', 'boolean'],
            'suspectedFaults.*.recommendedParts.*.compatibilityConfidence' => ['required', 'numeric', 'between:0,1'],
            'suspectedFaults.*.recommendedParts.*.searchKeywords.en' => ['required', 'string'], 'suspectedFaults.*.recommendedParts.*.searchKeywords.ar' => ['required', 'string'],
            'safeChecks.*.text.en' => ['required', 'string'], 'safeChecks.*.text.ar' => ['required', 'string'], 'safeChecks.*.stopCondition.en' => ['required', 'string'], 'safeChecks.*.stopCondition.ar' => ['required', 'string'],
            'recommendedActions.*.code' => ['required', 'string'], 'recommendedActions.*.text.en' => ['required', 'string'], 'recommendedActions.*.text.ar' => ['required', 'string'],
            'recommendedActions.*.priority' => ['required', 'integer', 'between:1,5'], 'recommendedActions.*.professionalRequired' => ['required', 'boolean'],
        ])->after(function ($validator) use ($data): void {
            $this->assertExactKeys($validator, $data, ['title', 'summary', 'overallConfidence', 'severity', 'drivingRecommendation', 'drivingAdvice', 'evidenceQuality', 'professionalInspectionRequired', 'emergencyWarnings', 'suspectedFaults', 'safeChecks', 'recommendedActions', 'limitations', 'missingEvidence'], 'root');
            foreach (['title', 'summary', 'drivingAdvice'] as $key) {
                $this->assertExactKeys($validator, $data[$key] ?? [], ['en', 'ar'], $key);
            }
            foreach ($data['limitations'] ?? [] as $i => $item) {
                $this->assertExactKeys($validator, $item, ['en', 'ar'], "limitations.$i");
            }
            foreach ($data['emergencyWarnings'] ?? [] as $i => $item) {
                $this->assertExactKeys($validator, $item, ['en', 'ar'], "emergencyWarnings.$i");
            }
            foreach ($data['suspectedFaults'] ?? [] as $faultIndex => $fault) {
                $path = "suspectedFaults.$faultIndex";
                $this->assertExactKeys($validator, $fault, ['canonicalCode', 'obdCode', 'title', 'description', 'confidence', 'severity', 'evidence', 'possibleCauses', 'recommendedActions', 'recommendedParts'], $path);
                $this->assertExactKeys($validator, $fault['title'] ?? [], ['en', 'ar'], "$path.title");
                $this->assertExactKeys($validator, $fault['description'] ?? [], ['en', 'ar'], "$path.description");
                foreach ($fault['evidence'] ?? [] as $index => $evidence) {
                    $this->assertExactKeys($validator, $evidence, ['sourceType', 'referenceId', 'observation', 'reliability'], "$path.evidence.$index");
                    $this->assertExactKeys($validator, $evidence['observation'] ?? [], ['en', 'ar'], "$path.evidence.$index.observation");
                }
                foreach ($fault['possibleCauses'] ?? [] as $index => $cause) {
                    $this->assertExactKeys($validator, $cause, ['en', 'ar'], "$path.possibleCauses.$index");
                }
                foreach ($fault['recommendedActions'] ?? [] as $index => $action) {
                    $this->assertExactKeys($validator, $action, ['code', 'text', 'priority', 'professionalRequired'], "$path.recommendedActions.$index");
                    $this->assertExactKeys($validator, $action['text'] ?? [], ['en', 'ar'], "$path.recommendedActions.$index.text");
                }
                foreach ($fault['recommendedParts'] ?? [] as $index => $part) {
                    $this->assertExactKeys($validator, $part, ['canonicalName', 'name', 'reason', 'partNumber', 'required', 'compatibilityConfidence', 'searchKeywords'], "$path.recommendedParts.$index");
                    foreach (['name', 'reason', 'searchKeywords'] as $field) {
                        $this->assertExactKeys($validator, $part[$field] ?? [], ['en', 'ar'], "$path.recommendedParts.$index.$field");
                    }
                }
            }
            foreach ($data['safeChecks'] ?? [] as $index => $check) {
                $this->assertExactKeys($validator, $check, ['text', 'stopCondition'], "safeChecks.$index");
                $this->assertExactKeys($validator, $check['text'] ?? [], ['en', 'ar'], "safeChecks.$index.text");
                $this->assertExactKeys($validator, $check['stopCondition'] ?? [], ['en', 'ar'], "safeChecks.$index.stopCondition");
            }
            foreach ($data['recommendedActions'] ?? [] as $index => $action) {
                $this->assertExactKeys($validator, $action, ['code', 'text', 'priority', 'professionalRequired'], "recommendedActions.$index");
                $this->assertExactKeys($validator, $action['text'] ?? [], ['en', 'ar'], "recommendedActions.$index.text");
            }
        })->validate();

        return $data;
    }

    private function assertExactKeys($validator, mixed $value, array $keys, string $path): void
    {
        if (! is_array($value) || array_diff(array_keys($value), $keys) !== [] || array_diff($keys, array_keys($value)) !== []) {
            $validator->errors()->add($path, 'The object contains missing or unsupported properties.');
        }
    }
}
