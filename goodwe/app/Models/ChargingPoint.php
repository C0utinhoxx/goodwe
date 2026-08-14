<?php

namespace App\Models;

use Carbon\CarbonImmutable;

/**
 * Represents a single EV charging point in the simulation.
 *
 * This is a plain data object (not an Eloquent model) because the
 * simulation runs entirely in memory, without a database.
 */
class ChargingPoint
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $location,
        public readonly int $powerKw,
        public readonly string $connector,
        public ?CarbonImmutable $occupiedUntil = null,
        public ?string $vehiclePlate = null,
    ) {}

    public function status(): string
    {
        if ($this->occupiedUntil !== null && $this->occupiedUntil->isFuture()) {
            return self::STATUS_OCCUPIED;
        }

        return self::STATUS_AVAILABLE;
    }

    public function isAvailable(): bool
    {
        return $this->status() === self::STATUS_AVAILABLE;
    }

    public function availableInMinutes(): ?int
    {
        if ($this->occupiedUntil === null) {
            return null;
        }

        $minutes = $this->occupiedUntil->diffInMinutes(now());

        return max(0, $minutes);
    }

    public function occupy(string $vehiclePlate, int $durationMinutes): void
    {
        $this->vehiclePlate = $vehiclePlate;
        $this->occupiedUntil = now()->toImmutable()->addMinutes($durationMinutes);
    }

    public function release(): void
    {
        $this->vehiclePlate = null;
        $this->occupiedUntil = null;
    }
}
