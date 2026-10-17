<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TariffCalculator
{
    public static function adjusted(string $base, string $percentage): string
    {
        $baseUnits = EnergyBilling::units($base);
        if (! preg_match('/^([0-9]{1,5})(?:\.([0-9]{1,2}))?$/D', $percentage, $parts) || $baseUnits <= 0 || $baseUnits > 99999999) {
            throw ValidationException::withMessages(['surge_percentage' => 'Tarifa ou percentual fora da precisão suportada.']);
        }
        $percentageUnits = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        $cents = intdiv($baseUnits * (10000 + $percentageUnits) + 500000, 1000000);
        $units = $percentageUnits === 0 ? $baseUnits : max($baseUnits, $cents * 100);
        if ($units > 99999999) {
            throw ValidationException::withMessages(['surge_percentage' => 'O reajuste resulta em uma tarifa fora da capacidade de armazenamento.']);
        }

        return intdiv($units, 10000).'.'.str_pad((string) ($units % 10000), 4, '0', STR_PAD_LEFT);
    }
}
