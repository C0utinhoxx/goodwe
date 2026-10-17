<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand' => 'BYD',
            'model' => 'Dolphin',
            'plate' => fake()->unique()->regexify('[A-Z]{3}[0-9][A-Z][0-9]{2}'),
            'battery_capacity_kwh' => 44.9,
        ];
    }
}
