<?php

namespace App\Services;

use App\Models\ChargingPoint;

/**
 * In-memory simulation of the EV charging network.
 *
 * Since this version runs without a database, the chargers live in a
 * static array kept in memory for the lifetime of the server process.
 * The simulation also "ages" over time: occupied chargers automatically
 * become available again once their scheduled session ends.
 */
class ChargingSimulator
{
    /**
     * @var array<string, ChargingPoint>|null
     */
    private static ?array $chargersByCode = null;

    /**
     * @return array<int, ChargingPoint>
     */
    public static function all(): array
    {
        $chargers = self::chargers();

        usort($chargers, fn (ChargingPoint $a, ChargingPoint $b) => strcmp($a->name, $b->name));

        return $chargers;
    }

    public static function find(string $code): ?ChargingPoint
    {
        return self::chargers()[$code] ?? null;
    }

    public static function occupy(string $code, string $vehiclePlate, int $durationMinutes): bool
    {
        $chargingPoint = self::find($code);

        if ($chargingPoint === null || ! $chargingPoint->isAvailable()) {
            return false;
        }

        $chargingPoint->occupy($vehiclePlate, $durationMinutes);

        return true;
    }

    public static function release(string $code): bool
    {
        $chargingPoint = self::find($code);

        if ($chargingPoint === null) {
            return false;
        }

        $chargingPoint->release();

        return true;
    }

    /**
     * Seeds the initial charger network.
     *
     * @return array<string, ChargingPoint>
     */
    private static function chargers(): array
    {
        return self::$chargersByCode ??= self::seed();
    }

    /**
     * @return array<string, ChargingPoint>
     */
    private static function seed(): array
    {
        $now = now()->toImmutable();

        $chargers = [
            new ChargingPoint(
                id: 'CG-001',
                name: 'Torre A - Norte',
                location: 'Parking Norte, Piso 1',
                powerKw: 22,
                connector: 'Type 2',
            ),
            new ChargingPoint(
                id: 'CG-002',
                name: 'Torre A - Sul',
                location: 'Parking Sul, Piso 2',
                powerKw: 11,
                connector: 'Type 2',
                occupiedUntil: $now->addMinutes(25),
                vehiclePlate: 'ABC-1D23',
            ),
            new ChargingPoint(
                id: 'CG-003',
                name: 'Estádio - Setor B',
                location: 'Estacionamento B, vaga 12',
                powerKw: 50,
                connector: 'CCS 2',
            ),
            new ChargingPoint(
                id: 'CG-004',
                name: 'Garagem Empresarial',
                location: 'Avenida Paulista, 1000',
                powerKw: 7,
                connector: 'Type 2',
                occupiedUntil: $now->addMinutes(70),
                vehiclePlate: 'XYZ-9Z87',
            ),
        ];

        foreach ($chargers as $charger) {
            self::$chargersByCode[$charger->id] = $charger;
        }

        return self::$chargersByCode;
    }
}
