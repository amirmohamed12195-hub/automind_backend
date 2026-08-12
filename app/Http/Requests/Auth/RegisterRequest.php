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
        $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'deviceName' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'in:en,ar'],
            'termsAccepted' => ['required', 'accepted'],
            'privacyAccepted' => ['required', 'accepted'],
            'legalVersion' => ['required', 'string', 'max:32', 'in:'.config('public.effective_date')],
        ];
    }
}
