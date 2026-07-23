<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $serviceDefinition = ['sometimes', 'nullable', 'ulid', 'exists:maintenance_service_definitions,id'];
        $customService = ['sometimes', 'nullable', 'string', 'max:160'];
        if ($this->isMethod('post')) {
            $serviceDefinition[] = 'required_without:customService';
            $customService[] = 'required_without:serviceDefinitionId';
        }

        return ['serviceDefinitionId' => $serviceDefinition, 'customService' => $customService, 'serviceDate' => [$required, 'date', 'before_or_equal:today'], 'odometerKm' => [$required, 'integer', 'between:0,5000000'], 'amount' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'required_with:currency'], 'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha', 'required_with:amount'], 'mechanic' => ['sometimes', 'nullable', 'string', 'max:160'], 'notes' => ['sometimes', 'nullable', 'string', 'max:2000'], 'nextDueDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:serviceDate'], 'nextDueKm' => ['sometimes', 'nullable', 'integer', 'between:0,5000000']];
    }
}
