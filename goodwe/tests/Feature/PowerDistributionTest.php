<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\Establishment;
use App\Models\User;
use App\Services\EstablishmentService;
use App\Services\PowerDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PowerDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PowerDistributionService
    {
        return app(PowerDistributionService::class);
    }

    private function establishmentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Loja de energia', 'cnpj' => '11.222.333/0001-81', 'address' => 'Rua 1',
            'phone' => '11987654321', 'type' => 'comercial', 'operating_hours' => '08:00–22:00',
            'cost_per_kwh' => '1.9000', 'pricing_mode' => 'manual', 'surge_percentage' => '15.00',
            'reservation_tolerance_minutes' => 15, 'no_show_penalty_points' => '10.00',
        ], $overrides);
    }

    private function store(array $overrides = []): Establishment
    {
        $manager = User::factory()->create(['is_admin' => true]);

        return app(EstablishmentService::class)->create($manager, $this->establishmentPayload($overrides));
    }

    private function charger(Establishment $establishment, array $overrides = []): Charger
    {
        return Charger::factory()->for($establishment)->create($overrides);
    }

    /**
     * @return array<int, array{id: int, name: string, requested_kw: float}>
     */
    private function requests(int $count, float $requestedKw): array
    {
        return array_map(
            fn (int $index): array => ['id' => $index + 1, 'name' => 'Carregador '.($index + 1), 'requested_kw' => $requestedKw],
            range(0, $count - 1)
        );
    }

    public function test_it_redistributes_equally_when_total_demand_exceeds_capacity(): void
    {
        $result = $this->service()->simulate(50.0, $this->requests(3, 22.0), 1.4);

        $this->assertTrue($result['balanced']);
        $this->assertSame('balanced', $result['status']);
        $this->assertTrue($result['start_allowed']);
        $this->assertSame(66.0, $result['total_requested_kw']);
        $this->assertSame(50.0, $result['allocated_total_kw']);
        $this->assertSame(16.0, $result['deficit_kw']);
        $this->assertSame(0.0, $result['unused_capacity_kw']);
        $this->assertEqualsWithDelta(16.667, $result['allocations'][0]['allocated_kw'], 0.001);
        $this->assertEqualsWithDelta(16.667, $result['allocations'][1]['allocated_kw'], 0.001);
        $this->assertEqualsWithDelta(16.666, $result['allocations'][2]['allocated_kw'], 0.001);
        $this->assertLessThanOrEqual(50.0, $result['allocated_total_kw']);
    }

    public function test_it_respects_individual_charger_limits_and_redistributes_the_remainder(): void
    {
        $result = $this->service()->simulate(40.0, [
            ['id' => 1, 'name' => 'Carregador pequeno', 'requested_kw' => 10.0],
            ['id' => 2, 'name' => 'Carregador A', 'requested_kw' => 22.0],
            ['id' => 3, 'name' => 'Carregador B', 'requested_kw' => 22.0],
        ], 1.4);

        $this->assertSame(40.0, $result['allocated_total_kw']);
        $this->assertSame([10.0, 15.0, 15.0], array_column($result['allocations'], 'allocated_kw'));
        $this->assertFalse($result['allocations'][0]['limited_by_capacity']);
        $this->assertTrue($result['allocations'][1]['limited_by_capacity']);
    }

    public function test_it_does_not_block_a_charger_that_was_fully_served_below_the_minimum(): void
    {
        $result = $this->service()->simulate(50.0, [
            ['id' => 1, 'name' => 'Carregador de baixa potência', 'requested_kw' => 1.0],
            ['id' => 2, 'name' => 'Carregador A', 'requested_kw' => 30.0],
            ['id' => 3, 'name' => 'Carregador B', 'requested_kw' => 30.0],
        ], 1.4);

        $this->assertTrue($result['balanced']);
        $this->assertSame('balanced', $result['status']);
        $this->assertTrue($result['start_allowed']);
        $this->assertFalse($result['allocations'][0]['below_minimum']);
        $this->assertSame([1.0, 24.5, 24.5], array_column($result['allocations'], 'allocated_kw'));
    }

    public function test_it_keeps_requested_power_when_demand_fits_the_capacity(): void
    {
        $result = $this->service()->simulate(50.0, $this->requests(2, 10.0), 1.4);

        $this->assertFalse($result['balanced']);
        $this->assertSame('within_capacity', $result['status']);
        $this->assertTrue($result['start_allowed']);
        $this->assertSame(20.0, $result['allocated_total_kw']);
        $this->assertSame(30.0, $result['unused_capacity_kw']);
        $this->assertSame(0.0, $result['deficit_kw']);
    }

    public function test_it_does_not_limit_anything_when_capacity_is_not_configured(): void
    {
        $result = $this->service()->simulate(null, $this->requests(3, 22.0), 1.4);

        $this->assertFalse($result['balanced']);
        $this->assertSame('unlimited', $result['status']);
        $this->assertSame(66.0, $result['allocated_total_kw']);
        $this->assertNull($result['unused_capacity_kw']);
        $this->assertTrue($result['start_allowed']);
    }

    public function test_it_blocks_new_sessions_when_the_minimum_power_cannot_be_met_after_balancing(): void
    {
        $result = $this->service()->simulate(2.0, $this->requests(2, 22.0), 1.4);

        $this->assertTrue($result['balanced']);
        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['start_allowed']);
        $this->assertSame(2.0, $result['allocated_total_kw']);
        $this->assertTrue($result['allocations'][0]['below_minimum']);
        $this->assertTrue($result['allocations'][1]['below_minimum']);
        $this->assertStringContainsString('bloqueadas', $result['message']);
    }

    public function test_it_can_allow_balancing_without_a_minimum_power(): void
    {
        $result = $this->service()->simulate(2.0, $this->requests(2, 22.0), 0.0);

        $this->assertSame('balanced', $result['status']);
        $this->assertTrue($result['start_allowed']);
        $this->assertSame(2.0, $result['allocated_total_kw']);
    }

    public function test_it_ignores_zero_requests_and_reports_empty_scenarios(): void
    {
        $result = $this->service()->simulate(50.0, [
            ['id' => 1, 'name' => 'Carregador A', 'requested_kw' => 0],
            ['id' => 2, 'name' => 'Carregador B', 'requested_kw' => -5],
        ], 1.4);

        $this->assertSame('empty', $result['status']);
        $this->assertSame([], $result['allocations']);
        $this->assertSame(0.0, $result['total_requested_kw']);
        $this->assertTrue($result['start_allowed']);
    }

    public function test_manager_panel_shows_the_balancing_simulation(): void
    {
        $store = $this->store(['max_power_kw' => '50.00']);
        $this->charger($store, ['name' => 'Carregador Alfa', 'power_kw' => 22]);
        $this->charger($store, ['name' => 'Carregador Beta', 'power_kw' => 22]);

        $this->actingAs($store->user)
            ->get(route('manager.demand-control.index'))
            ->assertOk()
            ->assertSee('Distribuição inteligente de energia')
            ->assertSee('Carregador Alfa')
            ->assertSee('Carregador Beta')
            ->assertSee('Demonstração');
    }

    public function test_manager_can_simulate_three_chargers_requesting_22_kw_each(): void
    {
        $store = $this->store(['max_power_kw' => '50.00']);
        $a = $this->charger($store, ['name' => 'Carregador A', 'power_kw' => 22]);
        $b = $this->charger($store, ['name' => 'Carregador B', 'power_kw' => 22]);
        $c = $this->charger($store, ['name' => 'Carregador C', 'power_kw' => 22]);

        $response = $this->actingAs($store->user)->get(route('manager.demand-control.simulate', [
            'chargers' => [
                $a->id => ['enabled' => '1', 'requested_kw' => '22'],
                $b->id => ['enabled' => '1', 'requested_kw' => '22'],
                $c->id => ['enabled' => '1', 'requested_kw' => '22'],
            ],
        ]));

        $response->assertOk()
            ->assertSee('Redistribuição necessária')
            ->assertSee('66,000')
            ->assertSee('50,000')
            ->assertSee('16,667');
    }

    public function test_simulation_only_considers_chargers_of_the_managers_establishment(): void
    {
        $store = $this->store(['max_power_kw' => '50.00']);
        $own = $this->charger($store, ['name' => 'Carregador próprio', 'power_kw' => 22]);
        $otherStore = $this->store(['cnpj' => '22.333.444/0001-92', 'max_power_kw' => '50.00']);
        $foreign = $this->charger($otherStore, ['name' => 'Carregador de outra loja', 'power_kw' => 22]);

        $this->actingAs($store->user)->get(route('manager.demand-control.simulate', [
            'chargers' => [
                $own->id => ['enabled' => '1', 'requested_kw' => '22'],
                $foreign->id => ['enabled' => '1', 'requested_kw' => '22'],
            ],
        ]))->assertOk()
            ->assertSee('Carregador próprio')
            ->assertDontSee('Carregador de outra loja');
    }

    public function test_simulation_rejects_invalid_requested_power(): void
    {
        $store = $this->store(['max_power_kw' => '50.00']);
        $charger = $this->charger($store, ['power_kw' => 22]);

        $this->actingAs($store->user)
            ->get(route('manager.demand-control.simulate', [
                'chargers' => [
                    $charger->id => ['enabled' => '1', 'requested_kw' => '0'],
                ],
            ]))
            ->assertSessionHasErrors('chargers.'.$charger->id.'.requested_kw');
    }

    public function test_simulation_reports_blocked_when_capacity_is_zero(): void
    {
        $store = $this->store(['max_power_kw' => '0.00']);
        $a = $this->charger($store, ['name' => 'Carregador A', 'power_kw' => 22]);
        $b = $this->charger($store, ['name' => 'Carregador B', 'power_kw' => 22]);

        $this->actingAs($store->user)->get(route('manager.demand-control.simulate', [
            'chargers' => [
                $a->id => ['enabled' => '1', 'requested_kw' => '22'],
                $b->id => ['enabled' => '1', 'requested_kw' => '22'],
            ],
        ]))->assertOk()
            ->assertSee('Nova recarga bloqueada')
            ->assertSee('0,000');
    }
}
