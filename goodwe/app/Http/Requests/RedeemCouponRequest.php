<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedeemCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['tier' => ['required', Rule::in(array_keys(config('loyalty.coupons')))], 'idempotency_key' => ['required', 'uuid']];
    }
}
