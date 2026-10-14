<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartChargingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['energy_kwh' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000'], 'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:100000'], 'charge_type' => ['required', 'in:full,partial'], 'target_percent' => ['nullable', 'numeric', 'min:1', 'max:100'], 'coupon_id' => ['nullable', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
    }
}
