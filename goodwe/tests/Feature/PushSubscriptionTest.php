<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createManager(): User
    {
        $manager = User::factory()->create([
            'is_admin' => true,
        ]);

        Establishment::create([
            'user_id' => $manager->id,
            'name' => 'Estabelecimento Teste',
            'cnpj' => '11.222.333/0001-81',
            'address' => 'Rua Teste',
            'phone' => '11987654321',
            'type' => 'comercial',
            'operating_hours' => '08:00-22:00',
            'cost_per_kwh' => '1.9000',
            'pricing_mode' => 'manual',
            'surge_percentage' => '20.00',
            'max_power_kw' => '100.00',
        ]);

        return $manager;
    }

    private function subscriptionData(
        string $endpoint = 'https://fcm.googleapis.com/fcm/send/teste-123'
    ): array {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'chave-publica-de-teste',
                'auth' => 'token-de-teste',
            ],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    public function test_manager_can_register_device(): void
    {
        $manager = $this->createManager();

        $data = $this->subscriptionData();

        $response = $this->actingAs($manager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $manager->id,
            'endpoint_hash' => hash('sha256', $data['endpoint']),
        ]);

        $subscription = PushSubscription::firstOrFail();

        $this->assertSame(
            $data['endpoint'],
            $subscription->endpoint
        );

        $this->assertSame(
            $data['keys']['p256dh'],
            $subscription->public_key
        );
    }

    public function test_normal_user_cannot_register_device(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(
                route('manager.notifications.subscribe'),
                $this->subscriptionData()
            );

        $response->assertForbidden();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_same_device_does_not_create_duplicates(): void
    {
        $manager = $this->createManager();

        $data = $this->subscriptionData();

        $this->actingAs($manager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertOk();

        $this->actingAs($manager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_device_cannot_be_registered_by_another_manager(): void
    {
        $firstManager = $this->createManager();

        $data = $this->subscriptionData();

        $this->actingAs($firstManager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertOk();

        $secondManager = User::factory()->create([
            'is_admin' => true,
        ]);

        Establishment::create([
            'user_id' => $secondManager->id,
            'name' => 'Segundo Estabelecimento',
            'cnpj' => '22.333.444/0001-92',
            'address' => 'Rua Dois',
            'phone' => '11912345678',
            'type' => 'comercial',
            'operating_hours' => '08:00-22:00',
            'cost_per_kwh' => '1.9000',
            'pricing_mode' => 'manual',
            'surge_percentage' => '20.00',
            'max_power_kw' => '100.00',
        ]);

        $this->actingAs($secondManager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertStatus(409);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $firstManager->id,
            'endpoint_hash' => hash('sha256', $data['endpoint']),
        ]);

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_manager_can_remove_own_device(): void
    {
        $manager = $this->createManager();

        $data = $this->subscriptionData();

        $this->actingAs($manager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertOk();

        $response = $this->actingAs($manager)
            ->deleteJson(
                route('manager.notifications.unsubscribe'),
                [
                    'endpoint' => $data['endpoint'],
                ]
            );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_manager_cannot_remove_another_managers_device(): void
    {
        $firstManager = $this->createManager();

        $data = $this->subscriptionData();

        $this->actingAs($firstManager)
            ->postJson(
                route('manager.notifications.subscribe'),
                $data
            )
            ->assertOk();

        $secondManager = User::factory()->create([
            'is_admin' => true,
        ]);

        Establishment::create([
            'user_id' => $secondManager->id,
            'name' => 'Segundo Estabelecimento',
            'cnpj' => '33.444.555/0001-03',
            'address' => 'Rua Tres',
            'phone' => '11999999999',
            'type' => 'comercial',
            'operating_hours' => '08:00-22:00',
            'cost_per_kwh' => '1.9000',
            'pricing_mode' => 'manual',
            'surge_percentage' => '20.00',
            'max_power_kw' => '100.00',
        ]);

        $this->actingAs($secondManager)
            ->deleteJson(
                route('manager.notifications.unsubscribe'),
                [
                    'endpoint' => $data['endpoint'],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $firstManager->id,
            'endpoint_hash' => hash('sha256', $data['endpoint']),
        ]);

        $this->assertDatabaseCount('push_subscriptions', 1);
    }
}
