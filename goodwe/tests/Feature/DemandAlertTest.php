<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\DemandAlert;
use App\Models\DemandAlertState;
use App\Models\Establishment;
use App\Models\User;
use App\Services\DemandAlertService;
use App\Services\MeteredChargingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandAlertTest extends TestCase
{
    use RefreshDatabase;

    private function createEstablishment(): Establishment
    {
        $manager = User::factory()->create([
            'is_admin' => true,
        ]);

        return Establishment::create([
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
    }

    private function createChargers(
        Establishment $establishment,
        int $total = 5,
        int $inUse = 4
    ): array {
        $chargers = [];

        for ($i = 1; $i <= $total; $i++) {
            $chargers[] = Charger::factory()
                ->for($establishment)
                ->create([
                    'name' => "Carregador {$i}",
                    'code' => "TESTE-{$i}",
                    'activation_status' => 'active',
                    'physical_status' => $i <= $inUse
                        ? 'in_use'
                        : 'available',
                ]);
        }

        return $chargers;
    }

    private function nextCheck(
        DemandAlertService $service,
        Establishment $establishment
    ): array {
        $this->travel(61)->seconds();

        return $service->check($establishment);
    }

    public function test_peak_is_confirmed_after_three_checks(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $service = app(DemandAlertService::class);

        $first = $service->check($establishment);

        $this->assertSame(0, $first['created']);

        $this->assertDatabaseCount('demand_alerts', 0);

        $this->nextCheck($service, $establishment);

        $this->assertDatabaseCount('demand_alerts', 0);

        $third = $this->nextCheck($service, $establishment);

        $this->assertSame(1, $third['created']);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'level' => DemandAlert::LEVEL_WARNING,
            'status' => DemandAlert::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('demand_alert_states', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'condition' => DemandAlertState::CONDITION_ACTIVE,
        ]);
    }

    public function test_active_peak_does_not_create_duplicates(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $service = app(DemandAlertService::class);

        $service->check($establishment);

        $this->nextCheck($service, $establishment);
        $this->nextCheck($service, $establishment);

        $this->assertDatabaseCount('demand_alerts', 1);

        $result = $this->nextCheck($service, $establishment);

        $this->assertSame(0, $result['created']);

        $this->assertDatabaseCount('demand_alerts', 1);
    }

    public function test_peak_is_resolved_after_three_normal_checks(): void
    {
        $establishment = $this->createEstablishment();

        $chargers = $this->createChargers($establishment);

        $service = app(DemandAlertService::class);

        $service->check($establishment);

        $this->nextCheck($service, $establishment);
        $this->nextCheck($service, $establishment);

        $this->assertDatabaseCount('demand_alerts', 1);

        foreach (array_slice($chargers, 0, 3) as $charger) {
            $charger->update([
                'physical_status' => 'available',
            ]);
        }

        $this->nextCheck($service, $establishment);
        $this->nextCheck($service, $establishment);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'status' => DemandAlert::STATUS_ACTIVE,
        ]);

        $result = $this->nextCheck($service, $establishment);

        $this->assertSame(1, $result['resolved']);

        $this->assertDatabaseHas('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_CHARGING_PEAK,
            'status' => DemandAlert::STATUS_RESOLVED,
        ]);
    }

    public function test_missing_energy_readings_do_not_create_energy_alert(): void
    {
        $establishment = $this->createEstablishment();

        $charger = $this->createChargers(
            $establishment,
            1,
            0
        )[0];

        $driver = User::factory()->create([
            'balance' => 500,
        ]);

        app(MeteredChargingService::class)->startConfirmed(
            $driver,
            $charger,
            'teste-leitura-ausente'
        );

        $service = app(DemandAlertService::class);

        $service->check($establishment);

        $this->nextCheck($service, $establishment);
        $this->nextCheck($service, $establishment);

        $this->assertDatabaseMissing('demand_alerts', [
            'establishment_id' => $establishment->id,
            'type' => DemandAlert::TYPE_ENERGY_PEAK,
        ]);
    }

    public function test_detector_does_not_change_tariffs(): void
    {
        $establishment = $this->createEstablishment();

        $this->createChargers($establishment);

        $originalTariff = $establishment->currentTariff();

        $service = app(DemandAlertService::class);

        $service->check($establishment);

        $this->nextCheck($service, $establishment);
        $this->nextCheck($service, $establishment);

        $establishment->refresh();

        $this->assertSame(
        $originalTariff,
        $establishment->currentTariff()
    );

        $this->assertNull($establishment->active_peak_id);

        $this->assertDatabaseCount('demand_peaks', 0);
    }
}
