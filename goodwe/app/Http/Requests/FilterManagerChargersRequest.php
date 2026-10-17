<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterManagerChargersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGestor() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }

    public function attributes(): array
    {
        return ['search' => 'busca'];
    }
}
