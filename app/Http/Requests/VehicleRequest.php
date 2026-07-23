<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'brand' => [$required, 'string', 'max:120'], 'model' => [$required, 'string', 'max:120'],
            'year' => [$required, 'integer', 'between:1886,'.((int) date('Y') + 1)], 'engine' => [$required, 'string', 'max:120'],
            'fuelType' => [$required, 'string', 'in:Petrol,Diesel,Hybrid,Electric,LPG,Other'],
            'transmission' => [$required, 'string', 'in:Manual,Automatic,CVT,DCT,Other'],
            'mileage' => [$required, 'integer', 'between:0,5000000'], 'vin' => ['sometimes', 'nullable', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            'plateNumber' => ['sometimes', 'nullable', 'string', 'max:64'], 'nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'catalogMakeId' => ['sometimes', 'nullable', 'ulid', 'exists:vehicle_makes,id'], 'catalogModelId' => ['sometimes', 'nullable', 'ulid', 'exists:vehicle_models,id'],
        ];
    }
}
