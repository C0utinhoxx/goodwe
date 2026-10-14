<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CouponFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'code' => 'PT-'.Str::uuid(), 'tier' => 'prata', 'name' => 'Prata', 'discount_percent' => 10, 'points_cost' => '25.00', 'status' => 'available', 'redemption_key' => Str::uuid(), 'redeemed_at' => now()];
    }
}
