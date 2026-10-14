<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EnergyBilling
{
    public static function units(string $value): int
    {
        if (! preg_match('/^(\d{1,8})(?:\.(\d{1,4}))?$/D', $value, $parts)) {
            throw ValidationException::withMessages(['energy' => 'Energia ou tarifa inválida; utilize até quatro casas decimais.']);
        }

        return (int) $parts[1] * 10000 + (int) str_pad($parts[2] ?? '', 4, '0');
    }

    public static function amount(string $energy, string $tariff): string
    {
        $energyUnits = self::units($energy);
        $tariffUnits = self::units($tariff);
        if ($tariffUnits <= 0 || ($energyUnits > 0 && $tariffUnits > intdiv(PHP_INT_MAX - 500000, $energyUnits))) {
            throw ValidationException::withMessages(['tariff' => 'Tarifa ou consumo fora da precisão suportada.']);
        }
        $cents = intdiv($energyUnits * $tariffUnits + 500000, 1000000);
        if ($cents > 9999999999) {
            throw ValidationException::withMessages(['energy' => 'Valor excede a capacidade do registro financeiro.']);
        }

        return DecimalAmount::decimal($cents);
    }
}
