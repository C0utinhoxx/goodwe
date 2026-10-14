<?php

namespace App\Services;

use InvalidArgumentException;

class DecimalAmount
{
    public static function units(string|int|float $value): int
    {
        $value = is_float($value) ? number_format($value, 2, '.', '') : (string) $value;
        if (! preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            throw new InvalidArgumentException('Valor decimal inválido.');
        }

        return ($parts[1] === '-' ? -1 : 1) * ((int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0'));
    }

    public static function decimal(int $units): string
    {
        return ($units < 0 ? '-' : '').intdiv(abs($units), 100).'.'.str_pad((string) (abs($units) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function percentage(int $units, int $percent): int
    {
        return intdiv($units * $percent + 50, 100);
    }
}
