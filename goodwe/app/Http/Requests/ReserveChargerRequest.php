<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveChargerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'duration_minutes' => ['prohibited'], 'starts_at' => ['prohibited'], 'reserved_at' => ['prohibited'], 'tariff_per_kwh' => ['prohibited']];
    }
}
