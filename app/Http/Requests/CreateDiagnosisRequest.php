<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicleId' => ['required', 'ulid', 'exists:vehicles,id'], 'description' => ['nullable', 'string', 'max:500', 'required_without:selectedSymptoms'],
            'selectedSymptoms' => ['nullable', 'array', 'max:9', 'required_without:description'], 'selectedSymptoms.*' => ['string', 'distinct', 'exists:symptom_definitions,code'],
            'inputLocale' => ['required', 'in:en,ar'], 'reportLocale' => ['required', 'in:en,ar'],
            'market' => ['nullable', 'array'], 'market.countryCode' => ['nullable', 'string', 'size:2', 'alpha'],
            'market.city' => ['nullable', 'string', 'max:120'], 'market.currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'clientReference' => ['nullable', 'string', 'max:120'], 'consentVersion' => ['required', 'string', 'max:32'],
        ];
    }
}
