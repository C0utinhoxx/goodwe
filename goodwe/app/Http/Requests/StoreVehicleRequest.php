<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $plate = $this->input('plate');
        if (is_string($plate)) {
            $this->merge(['plate' => strtoupper(str_replace('-', '', trim($plate)))]);
        }
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'plate' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', Rule::unique('vehicles', 'plate')->where('user_id', $this->user()->id)],
            'battery_capacity_kwh' => ['nullable', 'numeric', 'min:1', 'max:250'],
        ];
    }
}
