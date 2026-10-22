<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\DemandAlert;
use App\Models\Establishment;
use App\Models\User;
use App\Services\DemandAlertService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DemandPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createEstablishment(): Establishment
    {
        $manager = User::factory()->create([
            'is_admin' => true,
        ]);

        return Establishment::create([
            'user_id' => $manager->id,
            'name' => 'Estabelecimento Teste Push',
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
    }

    private function createChargers(
        Establishment $establishment
    ): void {
        for ($i = 1; $i <= 5; $i++) {
            Charger::factory()
                ->for($establishment)
                ->create([
                    'name' => "Carregador {$i}",
                    'code' => "PUSH-TESTE-{$i}",
                    'activation_status' => 'active',
                    'physical_status' => $i <= 4
                        ? 'in_use'
                        : 'available',
                ]);
        }
    }

    private function nextCheck(
        DemandAlertService $service,
        Establishment $establishment
    ): array {
        $this->travel(61)->seconds();

        return $service->check($establishment);
    }

    public function test_confirmed_peak_sends_only_one_push(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $originalTariff = $establishment->currentTariff();

        $push = Mockery::mock(WebPushService::class);

        $push->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function (
                User $user,
                string $title,
                string $message,
                string $url
            ) use ($establishment) {
                return $user->id === $establishment->user_id
                    && str_contains($title, 'Pico de recargas')
                    && str_contains($message, '80,00%')
                    && $url === '/gestor/demanda';
            })
            ->andReturn([
                'sent' => 1,
                'failed' => 0,
                'removed' => 0,
            ]);

        $this->app->instance(WebPushService::class, $push);

        $service = app(DemandAlertService::class);

        $first = $service->check($establishment);

        $this->assertSame(0, $first['created']);

        $second = $this->nextCheck(
            $service,
            $establishment
        );

        $this->assertSame(0, $second['created']);

        $third = $this->nextCheck(
            $service,
            $establishment
        );

        $this->assertSame(1, $third['created']);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'status' => DemandAlert::STATUS_ACTIVE,
        ]);

        $fourth = $this->nextCheck(
            $service,
            $establishment
        );

        $fifth = $this->nextCheck(
            $service,
            $establishment
        );

        $this->assertSame(0, $fourth['created']);
        $this->assertSame(0, $fifth['created']);

        $this->assertDatabaseCount('demand_alerts', 1);

        $establishment->refresh();

        $this->assertSame(
            $originalTariff,
            $establishment->currentTariff()
        );

        $this->assertDatabaseCount('demand_peaks', 0);

        $push->shouldHaveReceived('sendToUser')->once();
    }

    public function test_push_failure_does_not_prevent_alert_creation(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $push = Mockery::mock(WebPushService::class);

        $push->shouldReceive('sendToUser')
            ->once()
            ->andThrow(
                new \RuntimeException('Falha simulada no envio')
            );

        $this->app->instance(WebPushService::class, $push);

        $service = app(DemandAlertService::class);

        $service->check($establishment);

        $this->nextCheck($service, $establishment);

        $third = $this->nextCheck(
            $service,
            $establishment
        );

        $this->assertSame(1, $third['created']);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'status' => DemandAlert::STATUS_ACTIVE,
        ]);

        $push->shouldHaveReceived('sendToUser')->once();
    }
}
