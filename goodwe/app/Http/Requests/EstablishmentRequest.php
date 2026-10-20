<?php

namespace App\Http\Requests;

use App\Rules\Cnpj;
use App\Services\TariffCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class EstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGestor() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => Cnpj::normalize($this->input('cnpj'))]);
        }

        // Limites elétricos vazios significam "sem limite configurado".
        foreach (['max_power_kw', 'max_current_a'] as $limit) {
            if ($this->has($limit) && $this->input($limit) === '') {
                $this->merge([$limit => null]);
            }
        }
    }

    public function rules(): array
    {
        $existing = $this->isMethod('PATCH') ? $this->user()->establishment : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', new Cnpj, Rule::unique('establishments', 'cnpj')->ignore($existing)],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]+$/', 'min:10', 'max:20'],
            'type' => ['required', Rule::in(['residencial', 'comercial', 'shopping', 'outro'])],
            'operating_hours' => ['required', 'string', 'max:255'],
            'cost_per_kwh' => ['required', 'regex:/^[0-9]{1,4}(?:\.[0-9]{1,4})?$/D', 'numeric', 'gt:0'],
            'pricing_mode' => ['required', Rule::in(['manual', 'automatic'])],
            'surge_percentage' => ['required', 'regex:/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D'],
            'reservation_tolerance_minutes' => ['required', 'integer', 'min:1', 'max:'.max(1, intdiv(2147483647 - now()->timestamp, 60))],
            'no_show_penalty_points' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
            'max_power_kw' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_current_a' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'user_id' => ['prohibited'], 'establishment_id' => ['prohibited'], 'active_peak_id' => ['prohibited'], 'peak_tariff' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['cost_per_kwh', 'surge_percentage'])) {
                return;
            }
            try {
                TariffCalculator::adjusted((string) $this->input('cost_per_kwh'), (string) $this->input('surge_percentage'));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['required' => 'Informe :attribute.', 'unique' => ':attribute já está cadastrado.', 'prohibited' => 'O campo :attribute não pode ser alterado por este formulário.', 'regex' => 'Informe :attribute em um formato válido.', 'numeric' => 'Informe um valor numérico para :attribute.', 'integer' => 'Informe um número inteiro para :attribute.', 'gt' => ':attribute deve ser maior que :value.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'cnpj' => 'CNPJ', 'address' => 'endereço', 'phone' => 'telefone', 'type' => 'tipo de estabelecimento', 'operating_hours' => 'horário de funcionamento', 'cost_per_kwh' => 'tarifa-base', 'max_power_kw' => 'potência máxima', 'max_current_a' => 'corrente máxima', 'pricing_mode' => 'modo de tarifação', 'surge_percentage' => 'percentual de reajuste', 'reservation_tolerance_minutes' => 'tempo de tolerância', 'no_show_penalty_points' => 'penalidade em pontos', 'code' => 'identificador do equipamento', 'location' => 'localização', 'power_kw' => 'potência', 'connector' => 'conector'];
    }
}
