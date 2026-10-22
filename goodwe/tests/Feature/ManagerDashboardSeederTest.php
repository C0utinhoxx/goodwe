<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ManagerDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerDashboardSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-22 15:00:00');
        config()->set('manager.dashboard_cache_seconds', 0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_the_demo_manager_with_the_recharge_totems_and_dashboard_history(): void
    {
        $this->seed(ManagerDashboardSeeder::class);

        $manager = User::where('email', ManagerDashboardSeeder::MANAGER_EMAIL)->firstOrFail();

        $this->assertTrue($manager->is_admin);
        $this->assertTrue(Hash::check(ManagerDashboardSeeder::MANAGER_PASSWORD, $manager->password));

        $establishment = $manager->establishment;
        $this->assertNotNull($establishment);

        $chargers = Charger::query()
            ->where('establishment_id', $establishment->id)
            ->whereIn('code', ['totem_01', 'totem_02'])
            ->get();

        $this->assertCount(2, $chargers);
        $this->assertFalse($chargers->contains(fn (Charger $charger): bool => $charger->is_demo));
        $this->assertSame('available', $chargers->first()->status());

        $sessions = ChargingSession::query()
            ->where('establishment_id', $establishment->id)
            ->get();

        $this->assertGreaterThanOrEqual(300, $sessions->count());
        $this->assertTrue($sessions->every(fn (ChargingSession $session): bool => $session->is_demo));
        $this->assertTrue($sessions->every(
            fn (ChargingSession $session): bool => $chargers->contains('id', $session->charger_id)
        ));
        $this->assertGreaterThan(0, $sessions->where('status', ChargingSession::STATUS_COMPLETED)->count());

        $response = $this->actingAs($manager)->get(route('manager.dashboard', ['period' => 90]));

        $response->assertOk();
        $response->assertSee($establishment->name);
        $response->assertSee('Lab Goodwe 01');
        $response->assertSee('Lab Goodwe 02');
        $response->assertViewHas('periodSessionsCount', fn (int $count): bool => $count > 0);
        $response->assertViewHas('totalSessions', fn (int $count): bool => $count > 0);
        $response->assertViewHas('periodEnergy', fn (float $energy): bool => $energy > 0);
        $response->assertViewHas('periodRevenue', fn (float $revenue): bool => $revenue > 0);
        $response->assertViewHas('hasPeriodData', true);
    }

    public function test_running_the_seeder_again_replaces_only_the_fake_sessions(): void
    {
        $this->seed(ManagerDashboardSeeder::class);

        $manager = User::where('email', ManagerDashboardSeeder::MANAGER_EMAIL)->firstOrFail();
        $establishment = $manager->establishment;
        $driver = User::where('email', 'motorista1@powertag.test')->firstOrFail();
        $charger = Charger::where('code', 'totem_01')->firstOrFail();
        $demoSessions = ChargingSession::where('is_demo', true)->count();

        $realSession = ChargingSession::create([
            'user_id' => $driver->id,
            'establishment_id' => $establishment->id,
            'charger_id' => $charger->id,
            'charger_name' => $charger->name,
            'energy_kwh' => 12.5,
            'amount_charged' => 23.13,
            'payment_method' => ChargingSession::PAYMENT_WALLET,
            'status' => ChargingSession::STATUS_COMPLETED,
            'payment_status' => 'paid',
        ]);

        $this->seed(ManagerDashboardSeeder::class);

        $this->assertSame(1, User::where('email', ManagerDashboardSeeder::MANAGER_EMAIL)->count());
        $this->assertSame(1, Establishment::where('user_id', $manager->id)->count());
        $this->assertSame(2, Charger::whereIn('code', ['totem_01', 'totem_02'])->count());
        $this->assertSame($demoSessions, ChargingSession::where('is_demo', true)->count());
        $this->assertDatabaseHas('charging_sessions', ['id' => $realSession->id, 'is_demo' => false]);
    }

    public function test_production_seeder_is_skipped_after_the_first_demo_load(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', [
            '--class' => ManagerDashboardSeeder::class,
            '--force' => true,
        ])->assertSuccessful();

        $sessionIds = ChargingSession::where('is_demo', true)->orderBy('id')->pluck('id')->all();

        $this->assertNotEmpty($sessionIds);

        $this->artisan('db:seed', [
            '--class' => ManagerDashboardSeeder::class,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame($sessionIds, ChargingSession::where('is_demo', true)->orderBy('id')->pluck('id')->all());
    }
}
