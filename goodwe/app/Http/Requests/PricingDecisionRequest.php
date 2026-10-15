<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PricingDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGestor() && $this->route('peak')->establishment_id === $this->user()->establishment->id;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approve', 'reject'])], 'version' => ['required', 'integer', 'min:1']];
    }
}
