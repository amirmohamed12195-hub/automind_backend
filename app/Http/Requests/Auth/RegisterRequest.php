<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s()-]+/', '', trim((string) $this->phone));
        $countryCode = strtoupper(trim((string) $this->countryCode));

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->email)),
            'phone' => $phone !== '' ? $phone : null,
            'countryCode' => $countryCode !== '' ? $countryCode : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'max:16', 'unique:users,phone'],
            'countryCode' => ['nullable', 'required_with:phone', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'deviceName' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'in:en,ar'],
            'termsAccepted' => ['required', 'accepted'],
            'privacyAccepted' => ['required', 'accepted'],
            'legalVersion' => ['required', 'string', 'max:32', 'in:'.config('public.effective_date')],
        ];
    }
}
