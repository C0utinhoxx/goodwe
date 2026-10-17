<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public static function normalize(string $value): string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-[0-9]{2}$/D', $value)) {
            return str_replace(['.', '/', '-'], '', $value);
        }

        return $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Informe um CNPJ válido.');

            return;
        }
        $value = self::normalize($value);
        if (! preg_match('/^[A-Z0-9]{12}[0-9]{2}$/D', $value) || preg_match('/^(.)\1{13}$/D', $value)) {
            $fail('Informe um CNPJ válido, numérico ou alfanumérico.');

            return;
        }
        $base = substr($value, 0, 12);
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (ord($base[$i]) - 48) * $weight;
            }
            $remainder = $sum % 11;
            $base .= $remainder < 2 ? '0' : (string) (11 - $remainder);
        }
        if ($base !== $value) {
            $fail('Os dígitos verificadores do CNPJ são inválidos.');
        }
    }
}
