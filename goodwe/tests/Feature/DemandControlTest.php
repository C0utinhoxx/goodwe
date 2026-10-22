<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\Establishment;
use App\Models\User;
use App\Services\DemandControlService;
use App\Services\EstablishmentService;
use App\Services\FirestoreService;
use App\Services\MeteredChargingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeFirestoreService;
use Tests\TestCase;

class DemandControlTest extends TestCase
{
    use RefreshDatabase;

    private FakeFirestoreService $firestore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firestore = new FakeFirestoreService;
        $this->app->instance(FirestoreService::class, $this->firestore);
    }

    private function establishmentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Loja de demanda',
            'cnpj' => '11.222.333/0001-81',
            'address' => 'Rua 1',
            'phone' => '11987654321',
            'type' => 'comercial',
            'operating_hours' => '08:00–22:00',
            'cost_per_kwh' => '1.9000',
            'pricing_mode' => 'manual',
            'surge_percentage' => '15.00',
            'reservation_tolerance_minutes' => 15,
            'no_show_penalty_points' => '10.00',
        ], $overrides);
    }

    private function store(array $overrides = []): Establishment
    {
        $manager = User::factory()->create(['is_admin' => true]);

        return app(EstablishmentService::class)->create(
            $manager,
            $this->establishmentPayload($overrides)
        );
    }

    private function charger(
        Establishment $establishment,
        array $overrides = []
    ): Charger {
        return Charger::factory()
            ->for($establishment)
            ->create($overrides);
    }

    private function beginChargingSession(
        Establishment $establishment,
        Charger $charger,
        User $user
    ): void {
        app(MeteredChargingService::class)->startConfirmed(
            $user,
            $charger,
            (string) Str::uuid()
        );
    }

    private function driver(): User
    {
        return User::factory()->create(['balance' => 500]);
    }

    public function test_reading_is_recorded_idempotently_and_divergent_data_is_rejected(): void
    {
        $store = $this->store();
        $charger = $this->charger($store);
        $service = app(DemandControlService::class);

        $payload = [
            'external_id' => 'totem-1',
            'active_power_kw' => 10.5,
            'current_a' => 28.868,
            'recorded_at' => now(),
        ];

        $first = $service->recordReading($store, $charger, $payload);
        $second = $service->recordReading($store, $charger, $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('demand_readings', 1);

        $this->expectException(ValidationException::class);

        $service->recordReading($store, $charger, [
            'external_id' => 'totem-1',
            'active_power_kw' => 99,
            'recorded_at' => now(),
        ]);
    }

    public function test_reading_from_another_establishment_charger_is_rejected(): void
    {
        $store = $this->store();
        $other = $this->store([
            'cnpj' => '22.333.444/0001-92',
        ]);

        $foreign = $this->charger($other);

        $this->expectException(ValidationException::class);

        app(DemandControlService::class)->recordReading(
            $store,
            $foreign,
            [
                'external_id' => 'x',
                'active_power_kw' => 1,
            ]
        );
    }

    public function test_totals_aggregate_only_chargers_with_active_sessions(): void
    {
        $store = $this->store(['max_power_kw' => '100.00']);

        $a = $this->charger($store);
        $b = $this->charger($store);
        $idle = $this->charger($store);

        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);
        $this->beginChargingSession($store, $b, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 10,
            'current_a' => 26.24,
            'recorded_at' => now(),
        ]);

        $service->recordReading($store, $b, [
            'external_id' => 'b',
            'active_power_kw' => 15,
            'current_a' => 39.36,
            'recorded_at' => now(),
        ]);

        $service->recordReading($store, $idle, [
            'external_id' => 'idle',
            'active_power_kw' => 80,
            'current_a' => 200,
            'recorded_at' => now(),
        ]);

        $this->assertSame(
            25.0,
            $service->totalActivePowerKw($store)
        );

        $this->assertSame(
            65.6,
            $service->totalActiveCurrentA($store)
        );

        $this->assertCount(2, $service->activeSessions($store));
    }

    public function test_stale_readings_outside_the_window_are_ignored(): void
    {
        $store = $this->store(['max_power_kw' => '100.00']);
        $charger = $this->charger($store);
        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $charger, $driver);

        $service->recordReading($store, $charger, [
            'external_id' => 'old',
            'active_power_kw' => 50,
            'recorded_at' => now()->subMinutes(10),
        ]);

        $this->assertSame(
            0.0,
            $service->totalActivePowerKw($store)
        );
    }

    public function test_starting_a_session_is_blocked_when_it_would_exceed_power_capacity(): void
    {
        $store = $this->store(['max_power_kw' => '22.00']);
        $a = $this->charger($store, ['power_kw' => 22]);
        $b = $this->charger($store, ['power_kw' => 22]);

        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 22,
            'current_a' => 57.735,
            'recorded_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/capacidade/');

        app(MeteredChargingService::class)->startConfirmed(
            $driver,
            $b,
            'sessao-bloqueada'
        );
    }

    public function test_starting_a_session_is_blocked_when_it_would_exceed_current_capacity(): void
    {
        $store = $this->store(['max_current_a' => '60.00']);
        $a = $this->charger($store, ['power_kw' => 22]);
        $b = $this->charger($store, ['power_kw' => 22]);

        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 22,
            'current_a' => 58.0,
            'recorded_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(MeteredChargingService::class)->startConfirmed(
            $driver,
            $b,
            'corrente-bloqueada'
        );
    }

    public function test_starting_a_session_is_allowed_within_capacity(): void
    {
        $store = $this->store(['max_power_kw' => '60.00']);
        $a = $this->charger($store, ['power_kw' => 22]);
        $b = $this->charger($store, ['power_kw' => 22]);

        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 22,
            'current_a' => 57.735,
            'recorded_at' => now(),
        ]);

        $session = app(MeteredChargingService::class)->startConfirmed(
            $driver,
            $b,
            'sessao-permitida'
        );

        $this->assertNotNull($session->id);
        $this->assertCount(2, $service->activeSessions($store));
    }

    public function test_without_configured_limits_no_blocking_happens(): void
    {
        $store = $this->store();

        $a = $this->charger($store, ['power_kw' => 22]);
        $b = $this->charger($store, ['power_kw' => 999]);

        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 500,
            'current_a' => 1300,
            'recorded_at' => now(),
        ]);

        $session = app(MeteredChargingService::class)->startConfirmed(
            $driver,
            $b,
            'sem-limite'
        );

        $this->assertNotNull($session->id);

        $this->assertFalse(
            $service->status($store)['limit_configured']
        );
    }

    public function test_status_reports_totals_headroom_and_overload(): void
    {
        $store = $this->store([
            'max_power_kw' => '30.00',
            'max_current_a' => '80.00',
        ]);

        $a = $this->charger($store);
        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 32,
            'current_a' => 70,
            'recorded_at' => now(),
        ]);

        $status = $service->status($store);

        $this->assertTrue($status['overload']);
        $this->assertTrue($status['limit_configured']);

        $this->assertSame(
            32.0,
            $status['total_active_power_kw']
        );

        $this->assertSame(
            70.0,
            $status['total_active_current_a']
        );

        $this->assertSame(
            -2.0,
            $status['power_headroom_kw']
        );

        $this->assertSame(
            1,
            $status['active_sessions_count']
        );
    }

    public function test_status_uses_a_bounded_number_of_queries_instead_of_n_plus_one(): void
    {
        $store = $this->store();
        $service = app(DemandControlService::class);
        $driver = $this->driver();

        foreach (range(1, 4) as $index) {
            $charger = $this->charger($store, [
                'name' => "Carregador {$index}",
            ]);

            $this->beginChargingSession(
                $store,
                $charger,
                $driver
            );

            $service->recordReading($store, $charger, [
                'external_id' => "reading-{$index}",
                'active_power_kw' => 10,
                'current_a' => 20,
                'recorded_at' => now(),
            ]);
        }

        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $status = $service->status($store);

        $this->assertCount(4, $status['active_sessions']);
        $this->assertCount(4, $status['readings']);

        $this->assertSame(
            40.0,
            $status['total_active_power_kw']
        );

        $this->assertLessThanOrEqual(
            5,
            count($queries)
        );

        $this->assertCount(
            1,
            array_filter(
                $queries,
                fn (string $sql): bool =>
                    str_contains($sql, 'demand_readings')
            )
        );
    }

    public function test_estimated_current_uses_three_phase_math(): void
    {
        $this->assertEqualsWithDelta(
            57.735,
            DemandControlService::estimateCurrentA(22),
            0.001
        );

        $this->assertSame(
            0.0,
            DemandControlService::estimateCurrentA(0)
        );
    }

    public function test_manager_can_configure_electrical_limits_on_establishment(): void
    {
        $store = $this->store();

        $payload = $this->establishmentPayload([
            'max_power_kw' => '45.00',
            'max_current_a' => '120.00',
        ]);

        $this->actingAs($store->user)
            ->patch(
                route('manager.establishment.update'),
                $payload
            )
            ->assertRedirect(
                route('manager.establishment.edit')
            );

        $store->refresh();

        $this->assertSame('45.00', $store->max_power_kw);
        $this->assertSame('120.00', $store->max_current_a);
        $this->assertTrue($store->hasDemandLimit());
    }

    public function test_manager_demand_control_panel_shows_live_totals(): void
    {
        $store = $this->store([
            'max_power_kw' => '50.00',
        ]);

        $a = $this->charger($store);
        $driver = $this->driver();
        $service = app(DemandControlService::class);

        $this->beginChargingSession($store, $a, $driver);

        $service->recordReading($store, $a, [
            'external_id' => 'a',
            'active_power_kw' => 12.5,
            'current_a' => 32.8,
            'recorded_at' => now(),
        ]);

        $this->actingAs($store->user)
            ->get(route('manager.demand-control.index'))
            ->assertOk()
            ->assertSee('id="leituras-totens"', false)
            ->assertSee('id="sessoes-em-andamento"', false)
            ->assertSee('12,500')
            ->assertSee('32,800')
            ->assertSee($a->name);
    }

    public function test_api_endpoint_records_reading_and_returns_summary(): void
    {
        $store = $this->store([
            'max_power_kw' => '40.00',
        ]);

        $a = $this->charger($store, [
            'code' => 'GW-DEMAND-1',
        ]);

        $driver = $this->driver();

        $this->beginChargingSession(
            $store,
            $a,
            $driver
        );

        $response = $this->actingAs($store->user, 'sanctum')
            ->postJson('/api/demand/readings', [
                'charger_code' => 'GW-DEMAND-1',
                'external_id' => 'iot-1',
                'active_power_kw' => 18.5,
                'current_a' => 48.6,
                'source' => 'iot',
            ]);

        $response->assertCreated();

        $json = $response->json();

        $this->assertEquals(
            18.5,
            $json['reading']['active_power_kw']
        );

        $this->assertEquals(
            18.5,
            $json['summary']['total_active_power_kw']
        );

        $this->assertEquals(
            40,
            $json['summary']['max_power_kw']
        );

        $summary = $this->actingAs($store->user, 'sanctum')
            ->getJson('/api/demand/summary')
            ->assertOk()
            ->json();

        $this->assertEquals(
            18.5,
            $summary['total_active_power_kw']
        );

        $this->assertSame(
            1,
            $summary['active_sessions_count']
        );

        $this->assertDatabaseCount('demand_readings', 1);
        $this->assertSame(1, $a->demandReadings()->count());
    }

    public function test_api_endpoint_rejects_non_gestor_and_unknown_charger(): void
    {
        $store = $this->store();

        $this->charger($store, [
            'code' => 'GW-ONLY',
        ]);

        $driver = User::factory()->create();

        $this->actingAs($driver, 'sanctum')
            ->postJson('/api/demand/readings', [
                'charger_code' => 'GW-ONLY',
                'external_id' => 'x',
                'active_power_kw' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($store->user, 'sanctum')
            ->postJson('/api/demand/readings', [
                'charger_code' => 'INEXISTENTE',
                'external_id' => 'x',
                'active_power_kw' => 1,
            ])
            ->assertNotFound();
    }

    public function test_firestore_sync_records_totem_readings(): void
    {
        $store = $this->store([
            'max_power_kw' => '100.00',
        ]);

        $a = $this->charger($store, [
            'code' => 'GW-FIRE-1',
        ]);

        $driver = $this->driver();

        $this->beginChargingSession(
            $store,
            $a,
            $driver
        );

        $this->firestore->seed('totems', 'GW-FIRE-1', [
            'consumo_kw' => 20.0,
            'corrente_a' => 52.48,
            'tensao_v' => 220.0,
            'medido_em' => now()->toIso8601String(),
        ]);

        $count = app(DemandControlService::class)
            ->syncReadingsFromFirestore($store);

        $this->assertSame(1, $count);

        $this->assertDatabaseCount('demand_readings', 1);

        $reading = $a->demandReadings()->sole();

        $this->assertSame('firestore', $reading->source);

        $this->assertSame(
            20.0,
            (float) $reading->active_power_kw
        );

        $this->assertSame(
            20.0,
            app(DemandControlService::class)
                ->totalActivePowerKw($store)
        );
    }
}
