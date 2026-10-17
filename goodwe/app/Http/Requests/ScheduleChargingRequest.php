<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleChargingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $plate = $this->input('vehicle_plate');
        if (is_string($plate)) {
            $this->merge(['vehicle_plate' => strtoupper(trim($plate))]);
        }
    }

    public function rules(): array
    {
        return [
            'vehicle_plate' => ['required', 'string', 'max:8'],
            'duration_minutes' => ['required', 'integer', 'in:15,30,45,60,120'],
            'coupon_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
