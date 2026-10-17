<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PointMovementFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'type' => 'adjustment', 'amount' => '0.00', 'balance_after' => '0.00', 'description' => 'Ajuste de pontos', 'event_key' => Str::uuid()];
    }
}
