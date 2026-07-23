<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObdSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $speedMaximum = $this->input('units.speed', 'km/h') === 'mph' ? 310.686 : 500;
        $coolantRange = $this->input('units.coolantTemperature', 'celsius') === 'fahrenheit'
            ? '-58,428'
            : '-50,220';

        return [
            'recordedAt' => ['required', 'date'], 'troubleCodes' => ['nullable', 'array', 'max:64'],
            'troubleCodes.*' => ['string', 'regex:/^[PBCU][0-3A-F][0-9A-F]{3}$/i', 'distinct'],
            'rpm' => ['nullable', 'numeric', 'between:0,20000'], 'speed' => ['nullable', 'numeric', 'min:0', "max:$speedMaximum"],
            'coolantTemperature' => ['nullable', 'numeric', "between:$coolantRange"], 'batteryVoltage' => ['nullable', 'numeric', 'between:0,1000'],
            'fuelTrim' => ['nullable', 'numeric', 'between:-100,100'], 'engineLoad' => ['nullable', 'numeric', 'between:0,100'],
            'units' => ['nullable', 'array'],
            'units.speed' => ['nullable', 'in:km/h,mph'], 'units.coolantTemperature' => ['nullable', 'in:celsius,fahrenheit'],
        ];
    }
}
