<?php

namespace Tests\Feature\Api;

use App\Models\Transaction;
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
        $response->assertJsonPath('points', 2.5);
    }

    public function test_deposit_requires_valid_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallet/deposit', [
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_wallet_history_is_limited_and_signals_more_pages(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $index) {
            $user->transactions()->create([
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $index,
                'description' => "Deposito {$index}",
                'status' => Transaction::STATUS_APPROVED,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/wallet?per_page=4');

        $response->assertOk();
        $this->assertCount(4, $response->json('history'));
        $response->assertJsonPath('per_page', 4);
        $response->assertJsonPath('has_more', true);
    }

    public function test_wallet_history_uses_a_bounded_default_and_clamps_per_page(): void
    {
        $user = User::factory()->create();
        $user->transactions()->create([
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 10,
            'description' => 'Deposito',
            'status' => Transaction::STATUS_APPROVED,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('has_more', false);

        // Valores fora da faixa não passam do limite superior.
        $this->actingAs($user, 'sanctum')->getJson('/api/wallet?per_page=9999')
            ->assertOk()
            ->assertJsonPath('per_page', 200);
    }

    public function test_guest_cannot_view_wallet(): void
    {
        $response = $this->getJson('/api/wallet');

        $response->assertStatus(401);
    }
}
