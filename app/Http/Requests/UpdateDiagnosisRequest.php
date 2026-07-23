<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['description' => ['sometimes', 'nullable', 'string', 'max:500'], 'selectedSymptoms' => ['sometimes', 'array', 'max:9'], 'selectedSymptoms.*' => ['string', 'distinct', 'exists:symptom_definitions,code'], 'reportLocale' => ['sometimes', 'in:en,ar']];
    }
}
