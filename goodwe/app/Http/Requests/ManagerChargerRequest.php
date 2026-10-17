<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagerChargerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charger = $this->route('charger');

        return $this->user()?->isGestor() && (! $charger || $charger->establishment_id === $this->user()->establishment->id);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => $this->isMethod('PATCH') ? ['prohibited'] : ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D', Rule::unique('chargers', 'code')],
            'location' => ['required', 'string', 'max:255'],
            'power_kw' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'],
            'connector' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'establishment_id' => ['prohibited'], 'physical_status' => ['prohibited'], 'activation_status' => ['prohibited'],
            'physical_status_updated_at' => ['prohibited'], 'tariff_override' => ['prohibited'], 'active_reservation_id' => ['prohibited'], 'active_session_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Informe :attribute.', 'unique' => ':attribute já está cadastrado.', 'prohibited' => 'O campo :attribute não pode ser alterado por este formulário.', 'regex' => 'Informe :attribute em um formato válido.', 'numeric' => 'Informe um valor numérico para :attribute.', 'integer' => 'Informe um número inteiro para :attribute.', 'gt' => ':attribute deve ser maior que :value.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'cnpj' => 'CNPJ', 'address' => 'endereço', 'phone' => 'telefone', 'type' => 'tipo de estabelecimento', 'operating_hours' => 'horário de funcionamento', 'cost_per_kwh' => 'tarifa-base', 'pricing_mode' => 'modo de tarifação', 'surge_percentage' => 'percentual de reajuste', 'reservation_tolerance_minutes' => 'tempo de tolerância', 'no_show_penalty_points' => 'penalidade em pontos', 'code' => 'identificador do equipamento', 'location' => 'localização', 'power_kw' => 'potência', 'connector' => 'conector'];
    }
}
