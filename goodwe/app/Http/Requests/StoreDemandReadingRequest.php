<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemandReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isGestor() && $user->establishment !== null;
    }

    public function rules(): array
    {
        return [
            'charger_code' => ['required', 'string', 'max:255'],
            'external_id' => ['required', 'string', 'max:128'],
            'active_power_kw' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'current_a' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
            'voltage_v' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(['iot', 'firestore', 'manual'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'charger_code' => 'código do carregador',
            'external_id' => 'identificador da leitura',
            'active_power_kw' => 'potência ativa',
            'current_a' => 'corrente ativa',
            'voltage_v' => 'tensão',
            'recorded_at' => 'momento da leitura',
            'source' => 'origem',
        ];
    }
}
