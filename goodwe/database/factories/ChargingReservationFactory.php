<?php

namespace Database\Factories;

use App\Models\Charger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChargingReservationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'charger_id' => Charger::factory(), 'establishment_id' => fn (array $attrs) => Charger::findOrFail($attrs['charger_id'])->establishment_id, 'status' => 'active', 'idempotency_key' => Str::uuid(), 'tariff_per_kwh' => '1.9000', 'tolerance_seconds' => 900, 'no_show_penalty_points' => '10.00', 'reserved_at' => now(), 'expires_at' => now()->addMinutes(15)];
    }
}
