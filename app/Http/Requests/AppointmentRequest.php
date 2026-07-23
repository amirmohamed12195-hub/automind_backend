<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['mechanicId' => ['required', 'ulid', 'exists:mechanics,id'], 'vehicleId' => ['required', 'ulid', 'exists:vehicles,id'], 'reportId' => ['nullable', 'ulid', 'exists:diagnostic_reports,id'], 'requestedStart' => ['required', 'date', 'after:now'], 'requestedEnd' => ['required', 'date', 'after:requestedStart'], 'customerNote' => ['nullable', 'string', 'max:1000']];
    }
}
