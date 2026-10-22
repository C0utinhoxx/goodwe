<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\DemandAlert;
use App\Models\Establishment;
use App\Models\User;
use App\Services\EstablishmentService;
use App\Services\FirestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeFirestoreService;
use Tests\TestCase;

class MonitorDemandPeaksTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirestoreService $firestore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firestore = new FakeFirestoreService();

        $this->app->instance(
            FirestoreService::class,
            $this->firestore
        );
    }

    private function createEstablishment(): Establishment
    {
        $manager = User::factory()->create([
            'is_admin' => true,
        ]);

        return app(EstablishmentService::class)->create(
            $manager,
            [
                'name' => 'Loja Teste Monitoramento',
                'cnpj' => '11.222.333/0001-81',
                'address' => 'Rua Teste',
                'phone' => '11987654321',
                'type' => 'comercial',
                'operating_hours' => '08:00-22:00',
                'cost_per_kwh' => '1.9000',
                'pricing_mode' => 'manual',
                'surge_percentage' => '20.00',
                'reservation_tolerance_minutes' => 15,
                'no_show_penalty_points' => '10.00',
                'max_power_kw' => '100.00',
            ]
        );
    }

    private function createChargers(
        Establishment $establishment
    ): void {
        for ($i = 1; $i <= 5; $i++) {
            $charger = Charger::factory()
                ->for($establishment)
                ->create([
                    'name' => "Carregador {$i}",
                    'code' => "MONITOR-{$i}",
                    'activation_status' => 'active',
                    'physical_status' => $i <= 4
                        ? 'in_use'
                        : 'available',
                    'is_demo' => false,
                ]);

            $this->firestore->seed(
                'totems',
                $charger->code,
                [
                    'consumo_kw' => 10.0,
                    'corrente_a' => 26.24,
                    'tensao_v' => 220.0,
                    'medido_em' => now()->toIso8601String(),
                ]
            );
        }
    }

    public function test_command_syncs_firestore_without_real_credentials(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->assertDatabaseCount('demand_readings', 5);

        $this->assertDatabaseCount('demand_alerts', 0);
    }

    public function test_command_confirms_peak_and_does_not_duplicate_alerts(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $originalTariff = $establishment->currentTariff();

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->travel(61)->seconds();

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->travel(61)->seconds();

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'status' => DemandAlert::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseCount('demand_alerts', 1);

        $this->travel(61)->seconds();

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->assertDatabaseCount('demand_alerts', 1);

        $establishment->refresh();

        $this->assertSame(
            $originalTariff,
            $establishment->currentTariff()
        );
    }

    public function test_command_reports_firestore_failure(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $this->firestore->shouldFail = true;

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(1);

        $this->assertDatabaseCount('demand_readings', 0);

        $this->assertDatabaseCount('demand_alerts', 0);
    }

    public function test_command_ignores_old_firestore_readings(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        for ($i = 1; $i <= 5; $i++) {
            $this->firestore->seed(
                'totems',
                "MONITOR-{$i}",
                [
                    'consumo_kw' => 10.0,
                    'corrente_a' => 26.24,
                    'tensao_v' => 220.0,
                    'medido_em' => now()
                        ->subMinutes(10)
                        ->toIso8601String(),
                ]
            );
        }

        $this->artisan(
            'demand:monitor-peaks',
            ['--establishment' => $establishment->id]
        )->assertExitCode(0);

        $this->assertDatabaseCount('demand_readings', 0);
    }
}
