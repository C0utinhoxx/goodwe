<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EstablishmentFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->state(['is_admin' => true]), 'name' => fake()->company(), 'address' => fake()->address(), 'type' => 'comercial', 'operating_hours' => '08:00–22:00', 'cost_per_kwh' => '1.9000', 'reservation_tolerance_minutes' => 15, 'no_show_penalty_points' => '10.00'];
    }
}
