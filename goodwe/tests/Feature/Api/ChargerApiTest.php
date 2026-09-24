<?php

namespace Tests\Feature\Api;

use App\Models\ChargingPoint;
use App\Models\User;
use App\Services\ChargingSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_charger_list_is_public(): void
    {
        $response = $this->getJson('/api/chargers');

        $response->assertOk();
        $response->assertJsonStructure([
            'chargers' => [
                '*' => ['id', 'name', 'location', 'power_kw', 'connector', 'status', 'is_available', 'lat', 'lng'],
            ],
        ]);
    }

    public function test_authenticated_user_can_start_and_release_a_charge(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 0]);
        $charger = collect(ChargingSimulator::all())->first(fn (ChargingPoint $c) => $c->isAvailable());
        $this->assertNotNull($charger, 'Expected at least one available charger for the test.');

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/chargers/{$charger->id}/start", [
            'energy_kwh' => 5,
            'amount' => 9.5,
            'charge_type' => 'partial',
            'target_percent' => 60,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('session.amount_charged', 9.5);
        $response->assertJsonPath('user.balance', 90.5);

        $this->assertDatabaseHas('charging_sessions', [
            'user_id' => $user->id,
            'amount_charged' => 9.5,
            'status' => 'completed',
        ]);

        $this->assertFalse(ChargingSimulator::find($charger->id)->isAvailable());

        $releaseResponse = $this->actingAs($user, 'sanctum')->postJson("/api/chargers/{$charger->id}/release");
        $releaseResponse->assertOk();
        $this->assertTrue(ChargingSimulator::find($charger->id)->isAvailable());
    }

    public function test_start_fails_with_insufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 1, 'points' => 0]);
        $charger = collect(ChargingSimulator::all())->first(fn (ChargingPoint $c) => $c->isAvailable());
        $this->assertNotNull($charger);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/chargers/{$charger->id}/start", [
            'energy_kwh' => 20,
            'amount' => 38,
            'charge_type' => 'full',
            'target_percent' => 100,
        ]);

        $response->assertStatus(422);

        $this->assertTrue(ChargingSimulator::find($charger->id)->isAvailable());
    }

    public function test_start_fails_when_charger_is_unavailable(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $charger = collect(ChargingSimulator::all())->first(fn (ChargingPoint $c) => ! $c->isAvailable());
        $this->assertNotNull($charger, 'Expected at least one occupied charger for the test.');

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/chargers/{$charger->id}/start", [
            'energy_kwh' => 5,
            'amount' => 9.5,
            'charge_type' => 'partial',
            'target_percent' => 60,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_start_a_charge(): void
    {
        $charger = collect(ChargingSimulator::all())->first();

        $response = $this->postJson("/api/chargers/{$charger->id}/start", [
            'energy_kwh' => 5,
            'amount' => 9.5,
            'charge_type' => 'partial',
            'target_percent' => 60,
        ]);

        $response->assertStatus(401);
    }
}
