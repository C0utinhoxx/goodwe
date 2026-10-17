<?php

namespace Database\Factories;

use App\Models\DemandPeak;
use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandPeak>
 */
class DemandPeakFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(), 'external_id' => fake()->uuid(),
            'criteria_reference' => 'test-policy', 'evidence' => ['history' => ['sample_count' => 1], 'occupancy' => ['in_use' => 1]],
            'status' => 'active', 'decision' => 'pending', 'pricing_mode' => 'manual', 'base_tariff' => '1.9000',
            'suggested_tariff' => '2.1900', 'percentage' => '15.00', 'version' => 1, 'started_at' => now(),
        ];
    }
}
