<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'], 'email' => ['sometimes', 'email:rfc', Rule::unique('users')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('users')->ignore($this->user()?->id)],
            'countryCode' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'], 'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'], 'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
        ];
    }
}
