<?php

namespace Database\Factories;

use App\Models\Charger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargingSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'charger_id' => Charger::factory(), 'establishment_id' => fn (array $attrs) => Charger::findOrFail($attrs['charger_id'])->establishment_id, 'charger_name' => 'Carregador de teste', 'energy_kwh' => 30, 'measured_energy_kwh' => '30.0000', 'tariff_per_kwh' => '1.9000', 'amount_charged' => 0, 'original_amount' => 57, 'discount_amount' => 0, 'payment_method' => 'wallet', 'status' => 'pending', 'charging_state' => 'ended', 'payment_status' => 'pending', 'started_at' => now()->subHour(), 'ended_at' => now()];
    }
}
