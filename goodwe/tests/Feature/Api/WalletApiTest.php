<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_wallet(): void
    {
        $user = User::factory()->create(['balance' => 42.50, 'points' => 100]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/wallet');

        $response->assertOk();
        $response->assertJsonPath('balance', 42.5);
        $response->assertJsonPath('points', 100);
    }

    public function test_authenticated_user_can_deposit(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 0]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', [
            'amount' => 50,
        ]);

        $response->assertOk();
        $response->assertJsonPath('balance', 50);
        $response->assertJsonPath('points', 500);
    }

    public function test_deposit_requires_valid_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', [
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_view_wallet(): void
    {
        $response = $this->getJson('/api/wallet');

        $response->assertStatus(401);
    }
}
