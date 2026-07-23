<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiagnosticMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $imageKb = (int) ceil(config('automind.media.max_image_bytes') / 1024);
        $audioKb = (int) ceil(config('automind.media.max_audio_bytes') / 1024);

        return [
            'kind' => ['required', 'in:photo,engine_sound,spoken_description'],
            'file' => ['required', 'file', Rule::when($this->input('kind') === 'photo', ['max:'.$imageKb], ['max:'.$audioKb])],
        ];
    }
}
