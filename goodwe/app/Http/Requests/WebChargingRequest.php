<?php

namespace App\Http\Requests;

use App\Services\WebChargingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebChargingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isGestor() && WebChargingService::available();
    }

    public function rules(): array
    {
        return ['speed' => ['nullable', 'integer', Rule::in(WebChargingService::SPEEDS)]];
    }
}
