<?php

namespace Database\Factories;

use App\Models\Charger;
use App\Models\DemandReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandReading>
 */
class DemandReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'charger_id' => Charger::factory(),
            'establishment_id' => fn (array $attrs) => Charger::findOrFail($attrs['charger_id'])->establishment_id,
            'external_id' => 'reading-'.fake()->unique()->numerify('######'),
            'active_power_kw' => '10.000',
            'current_a' => '26.240',
            'voltage_v' => '220.00',
            'recorded_at' => now(),
            'source' => DemandReading::SOURCE_IOT,
        ];
    }
}
