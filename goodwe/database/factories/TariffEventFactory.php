<?php

namespace Database\Factories;

use App\Models\Establishment;
use App\Models\TariffEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffEvent>
 */
class TariffEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(), 'type' => 'configuration_created',
            'previous_tariff' => null, 'new_tariff' => '1.9000', 'percentage' => '15.00', 'pricing_mode' => 'manual', 'occurred_at' => now(),
        ];
    }
}
