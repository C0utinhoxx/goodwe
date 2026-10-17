<?php

namespace Database\Factories;

use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargerFactory extends Factory
{
    public function definition(): array
    {
        return ['establishment_id' => Establishment::factory(), 'code' => 'TEST-'.fake()->unique()->numerify('######'), 'name' => 'Carregador de teste', 'location' => 'Local de teste', 'power_kw' => 22, 'connector' => 'Type 2', 'activation_status' => 'active', 'physical_status' => 'available', 'physical_status_updated_at' => now()];
    }
}
