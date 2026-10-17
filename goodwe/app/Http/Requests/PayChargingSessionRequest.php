<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayChargingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'coupon_id' => ['nullable', 'integer', 'min:1']];
    }
}
