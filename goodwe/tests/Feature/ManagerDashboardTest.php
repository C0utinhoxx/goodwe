<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_metrics_share_the_same_period_and_establishment_scope(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        [$manager, $establishment] = $this->createManagerWithEstablishment();
        $firstUser = User::factory()->create(['name' => 'First User']);
        $secondUser = User::factory()->create(['name' => 'Second User']);

        $this->createSession($establishment, $firstUser, 'Torre Norte', ChargingSession::STATUS_COMPLETED, 10, 15, now()->subDay());
        $this->createSession($establishment, $firstUser, 'Torre Norte', ChargingSession::STATUS_PENDING, 8, 12, now()->subDays(2));
        $this->createSession($establishment, $secondUser, 'Torre Sul', ChargingSession::STATUS_COMPLETED, 20, 30, now()->subDays(3));
        $this->createSession($establishment, $secondUser, 'Torre Sul', ChargingSession::STATUS_CANCELLED, 4, 6, now()->subDays(4));
        $this->createSession($establishment, $firstUser, 'Torre Norte', ChargingSession::STATUS_COMPLETED, 40, 60, now()->subDays(20));

        [, $otherEstablishment] = $this->createManagerWithEstablishment();
        $this->createSession($otherEstablishment, $firstUser, 'Outro Local', ChargingSession::STATUS_COMPLETED, 999, 999, now()->subDay());

        $response = $this->actingAs($manager)->get(route('manager.dashboard', ['period' => 7]));

        $response->assertOk();
        $response->assertViewHas('periodSessionsCount', 4);
        $response->assertViewHas('periodRevenue', 45.0);
        $response->assertViewHas('periodEnergy', 30.0);
        $response->assertViewHas('periodActiveUsers', 2);
        $response->assertViewHas('totalSessions', 5);
        $response->assertViewHas('totalRevenue', 105.0);
        $response->assertViewHas('totalEnergy', 70.0);
        $response->assertViewHas('totalUsers', 2);
        $response->assertViewHas('chargerDistribution', fn ($rows) => $rows->sum('total') === 4);
        $response->assertViewHas('statusDistribution', fn ($rows) => $rows->sum('total') === 4);
        $response->assertViewHas('topUsers', fn ($rows) => $rows->sum('sessions_count') === 4);
        $response->assertViewHas('dailyKwh', fn ($values) => $values->sum() === 30.0);
        $response->assertViewHas('dailyRevenue', fn ($values) => $values->sum() === 45.0);

        $thirtyDayResponse = $this->actingAs($manager)->get(route('manager.dashboard', ['period' => 30]));

        $thirtyDayResponse->assertOk();
        $thirtyDayResponse->assertViewHas('periodSessionsCount', 5);
        $thirtyDayResponse->assertViewHas('periodRevenue', 105.0);
        $thirtyDayResponse->assertViewHas('periodEnergy', 70.0);
        $thirtyDayResponse->assertViewHas('chargerDistribution', fn ($rows) => $rows->sum('total') === 5);
        $thirtyDayResponse->assertViewHas('statusDistribution', fn ($rows) => $rows->sum('total') === 5);
    }

    public function test_dashboard_has_a_consistent_empty_state_for_a_period_without_sessions(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        [$manager, $establishment] = $this->createManagerWithEstablishment();
        $user = User::factory()->create();
        $this->createSession($establishment, $user, 'Torre Norte', ChargingSession::STATUS_COMPLETED, 10, 15, now()->subDays(120));

        $response = $this->withSession(['locale' => 'pt'])
            ->actingAs($manager)
            ->get(route('manager.dashboard', ['period' => 7]));

        $response->assertOk();
        $response->assertViewHas('periodSessionsCount', 0);
        $response->assertViewHas('periodRevenue', 0.0);
        $response->assertViewHas('periodEnergy', 0.0);
        $response->assertViewHas('periodActiveUsers', 0);
        $response->assertViewHas('hasPeriodData', false);
        $response->assertViewHas('hasCompletedData', false);
        $response->assertViewHas('chargerDistribution', fn ($rows) => $rows->isEmpty());
        $response->assertViewHas('statusDistribution', fn ($rows) => $rows->sum('total') === 0);
        $response->assertViewHas('topUsers', fn ($rows) => $rows->isEmpty());
        $response->assertSeeText('Nenhum dado disponível neste período.');
    }

    /**
     * @return array{User, Establishment}
     */
    public function test_dashboard_aggregate_metrics_are_cached_until_the_ttl_expires(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        config()->set('manager.dashboard_cache_seconds', 60);

        [$manager, $establishment] = $this->createManagerWithEstablishment();
        $user = User::factory()->create();
        $this->createSession($establishment, $user, 'Torre Norte', ChargingSession::STATUS_COMPLETED, 10, 15, now()->subDay());

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertViewHas('totalSessions', 1);

        // Sessões criadas durante a vigência do cache não alteram os indicadores.
        $this->createSession($establishment, $user, 'Torre Sul', ChargingSession::STATUS_COMPLETED, 20, 30, now()->subDay());

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertViewHas('totalSessions', 1);

        // Expirado o TTL, os agregados são recalculados.
        $this->travel(61)->seconds();

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertViewHas('totalSessions', 2)
            ->assertViewHas('totalEnergy', fn ($energy) => abs((float) $energy - 30.0) < 0.00001);
    }

    private function createManagerWithEstablishment(): array
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $establishment = Establishment::create([
            'user_id' => $manager->id,
            'name' => 'GoodWe Test',
            'address' => 'Test Address',
            'type' => Establishment::TYPE_COMERCIAL,
            'operating_hours' => '08:00 - 22:00',
            'cost_per_kwh' => 1.5,
        ]);

        return [$manager, $establishment];
    }

    private function createSession(
        Establishment $establishment,
        User $user,
        string $chargerName,
        string $status,
        float $energyKwh,
        float $amountCharged,
        Carbon $createdAt
    ): ChargingSession {
        $session = ChargingSession::create([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'charger_name' => $chargerName,
            'energy_kwh' => $energyKwh,
            'amount_charged' => $amountCharged,
            'payment_method' => ChargingSession::PAYMENT_WALLET,
            'status' => $status,
        ]);

        $session->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $session;
    }
}
