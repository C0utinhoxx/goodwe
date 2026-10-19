<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\MeteredChargingService;
use App\Services\ReservationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebChargingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        Http::preventStrayRequests();
        Http::fake();
        $this->mock(PaymentGatewayInterface::class, function ($mock): void {
            $mock->shouldNotReceive('createPreference');
            $mock->shouldNotReceive('findPayment');
        });
    }

    private function charger(): Charger
    {
        $store = Establishment::factory()->create(['cost_per_kwh' => '1.9000', 'reservation_tolerance_minutes' => 15, 'no_show_penalty_points' => '10.00']);

        return Charger::factory()->for($store)->create(['power_kw' => 30]);
    }

    private function deposit(int $amount = 100, ?string $key = null): void
    {
        $this->post(route('wallet.deposit.store'), ['amount' => $amount, 'idempotency_key' => $key ?? (string) Str::uuid()])->assertRedirect(route('wallet.index'))->assertSessionHasNoErrors();
    }

    private function reserve(Charger $charger): ChargingReservation
    {
        $response = $this->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertCreated();

        return ChargingReservation::findOrFail($response->json('reservation.id'));
    }

    private function start(ChargingReservation $reservation): ChargingSession
    {
        $response = $this->postJson(route('reservations.start-test', $reservation))->assertCreated();

        return ChargingSession::findOrFail($response->json('session.id'));
    }

    public function test_start_from_regular_reservation_uses_current_wallet_points_coupon_payment_and_history(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 220, 'points' => 5]);
        $this->actingAs($user);
        $this->get(route('user.home'))->assertOk()->assertDontSee('Entrar no modo demonstração')->assertDontSee('Modo demonstração');
        $this->get(route('wallet.deposit.form'))->assertOk()->assertSee('Continuar com Stripe')->assertSee('Adicionar crédito de teste');
        $key = (string) Str::uuid();
        $this->deposit(100, $key);
        $this->deposit(100, $key);
        $this->assertEquals(320, $user->refresh()->balance);
        $this->assertEquals(10, $user->points);
        $this->postJson(route('points.redeem'), ['tier' => 'bronze', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $coupon = $user->coupons()->sole();
        $reservation = $this->reserve($charger);
        $this->get(route('chargers.index'))->assertOk()->assertSee('Iniciar recarga')->assertSee(route('reservations.start-test', $reservation));
        $charger->establishment->update(['cost_per_kwh' => 9]);
        $session = $this->start($reservation);
        $this->postJson(route('reservations.start-test', $reservation))->assertCreated()->assertJsonPath('session.id', $session->id);
        $this->assertSame('1.9000', $session->tariff_per_kwh);
        $this->assertTrue($session->isWebSimulation());
        $this->get(route('charging-sessions.show', $session))->assertOk()->assertSee('Recarga em andamento')->assertSee('Encerrar recarga');
        $this->travel(300)->seconds();
        $this->postJson(route('charging-sessions.advance-test', $session), ['speed' => 60, 'energy_kwh' => 999999])->assertOk()->assertJsonPath('session.measured_energy_kwh', '2.5000');
        $this->travel(55)->seconds();
        $this->postJson(route('charging-sessions.end-test', $session))->assertOk()->assertJsonPath('session.measured_energy_kwh', '30.0000')->assertJsonPath('session.original_amount', '57.00')->assertJsonPath('session.payment_status', 'pending');
        $this->assertEquals(320, $user->refresh()->balance);
        $payment = ['idempotency_key' => (string) Str::uuid(), 'coupon_id' => $coupon->id];
        $this->postJson(route('charging-sessions.pay', $session), $payment)->assertOk()->assertJsonPath('session.amount_charged', '54.15')->assertJsonPath('session.discount_amount', '2.85');
        $this->postJson(route('charging-sessions.pay', $session), $payment)->assertOk();
        $this->assertEquals('265.85', $user->refresh()->balance);
        $this->assertEquals(0, $user->points);
        $this->assertSame('used', $coupon->refresh()->status);
        $this->assertNull($session->refresh()->stop_confirmed_at);
        $this->assertSame('available', $charger->refresh()->physical_status);
        $this->assertDatabaseCount('charging_sessions', 1);
        $this->assertDatabaseCount('transactions', 2);
        $this->get(route('wallet.history'))->assertOk()->assertSee('Histórico financeiro')->assertSee($charger->name)->assertSee('54,15')->assertSee('2,85')->assertSee('Recarga de teste · consumo simulado');
        foreach (ChargingEvent::where('charging_session_id', $session->id)->get() as $event) {
            $this->assertFalse($event->metadata['command_dispatched'] ?? false);
            $this->assertFalse($event->metadata['physical_confirmation'] ?? false);
        }
        Http::assertNothingSent();
    }

    public function test_start_requires_wallet_balance_and_own_unexpired_available_reservation(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 0]);
        $this->actingAs($user);
        $reservation = $this->reserve($charger);
        $this->postJson(route('reservations.start-test', $reservation))->assertUnprocessable();
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->actingAs(User::factory()->create(['balance' => 100]))->postJson(route('reservations.start-test', $reservation))->assertForbidden();
        $this->actingAs($user);
        $this->deposit();
        $charger->update(['physical_status' => 'unavailable']);
        $this->postJson(route('reservations.start-test', $reservation))->assertUnprocessable();
        $this->assertDatabaseCount('charging_sessions', 0);
        $charger->update(['physical_status' => 'available']);
        $this->travel(16)->minutes();
        $this->postJson(route('reservations.start-test', $reservation))->assertUnprocessable();
        $this->assertSame('expired', $reservation->refresh()->status);
        $this->assertEquals(0, $user->refresh()->points);
        $this->assertEquals(100, $user->balance);
    }

    public function test_reservation_conflicts_and_cancelled_reservations_cannot_be_started(): void
    {
        $charger = $this->charger();
        $this->actingAs(User::factory()->create(['balance' => 100]));
        $reservation = $this->reserve($charger);
        $this->postJson(route('reservations.store', $charger->code), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson(route('reservations.cancel', $reservation))->assertOk();
        $this->postJson(route('reservations.start-test', $reservation))->assertUnprocessable();
        $this->assertDatabaseCount('charging_sessions', 0);
    }

    public function test_consumption_ends_at_wallet_limit_and_payment_without_coupon_is_idempotent(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 1]);
        $this->actingAs($user);
        $session = $this->start($this->reserve($charger));
        $charger->update(['power_kw' => 7]);
        $this->travel(63)->seconds();
        $this->postJson(route('charging-sessions.advance-test', $session))->assertOk()->assertJsonPath('session.charging_state', 'ended')->assertJsonPath('session.measured_energy_kwh', '0.5250')->assertJsonPath('session.original_amount', '1.00')->assertJsonPath('session.simulation.power_kw', '30.00');
        $payment = ['idempotency_key' => (string) Str::uuid()];
        $this->postJson(route('charging-sessions.pay', $session), $payment)->assertOk();
        $this->postJson(route('charging-sessions.pay', $session), $payment)->assertOk();
        $this->assertEquals(0, $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
        $this->travel(1)->hour();
        $this->postJson(route('charging-sessions.end-test', $session))->assertOk()->assertJsonPath('session.measured_energy_kwh', '0.5250');
    }

    public function test_insufficient_payment_keeps_balance_coupon_and_session_unchanged(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 100, 'points' => 10]);
        $this->actingAs($user);
        $coupon = app(LoyaltyService::class)->redeem($user, 'bronze', (string) Str::uuid());
        $session = $this->start($this->reserve($charger));
        $this->travel(1)->hour();
        $this->postJson(route('charging-sessions.end-test', $session))->assertOk();
        $user->refresh()->update(['balance' => 0]);
        $this->postJson(route('charging-sessions.pay', $session), ['idempotency_key' => (string) Str::uuid(), 'coupon_id' => $coupon->id])->assertUnprocessable();
        $this->assertSame('available', $coupon->refresh()->status);
        $this->assertNull($session->refresh()->transaction_id);
        $this->assertEquals(0, $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_other_users_and_sessions_confirmed_by_iot_cannot_be_controlled_by_test_buttons(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);
        $session = $this->start($this->reserve($charger));
        $this->postJson(route('charging-sessions.advance-test', $session), ['speed' => 999])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson(route('charging-sessions.end-test', $session))->assertForbidden();
        $this->postJson(route('charging-sessions.advance-test', $session))->assertForbidden();
        $this->actingAs($user);
        $realCharger = Charger::factory()->create();
        $realReservation = $this->reserve($realCharger);
        $realSession = app(MeteredChargingService::class)->startConfirmed($user, $realCharger, 'physical-start', $realReservation->id);
        $this->postJson(route('reservations.start-test', $realReservation))->assertUnprocessable();
        $this->postJson(route('charging-sessions.end-test', $realSession))->assertForbidden();
        $this->assertSame('charging', $realSession->refresh()->charging_state);
        Http::assertNothingSent();
    }

    public function test_test_controls_are_local_only_and_no_standalone_mode_routes_exist(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 100]);
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $this->post(route('reservations.start-test', $reservation))->assertRedirect(route('user.login'));
        $this->actingAs($charger->establishment->user)->post(route('reservations.start-test', $reservation))->assertForbidden();
        $this->actingAs($user)->get('/demonstracao/ativar')->assertNotFound();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app->instance('env', 'production');
        $this->get(route('chargers.index'))->assertOk()->assertDontSee('Iniciar recarga</button>', false);
        $this->get(route('wallet.deposit.form'))->assertOk()->assertDontSee('Adicionar crédito de teste');
        $this->post(route('reservations.start-test', $reservation))->assertForbidden();
        $this->post(route('wallet.deposit.store'), ['amount' => 100])->assertForbidden();
        $this->assertDatabaseCount('charging_sessions', 0);
    }

    public function test_earlier_separate_demo_records_remain_archived_without_changing_the_current_ledger(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 220, 'points' => 0, 'demo_balance' => 75, 'demo_points' => 10]);
        $archived = Charger::factory()->create(['is_demo' => true, 'name' => 'Old isolated demo']);
        $user->transactions()->create(['amount' => 100, 'description' => 'OLD-DEMO-TRANSACTION', 'type' => 'deposit', 'status' => 'approved'])->forceFill(['is_demo' => true])->save();
        $this->actingAs($user)->withSession(['charging_demo' => true])->get(route('user.home'))->assertOk()->assertSee('220,00')->assertDontSee('Entrar no modo demonstração');
        $this->get(route('chargers.index'))->assertOk()->assertSee($charger->name)->assertDontSee($archived->name);
        $this->get(route('wallet.history'))->assertOk()->assertDontSee('OLD-DEMO-TRANSACTION');
        $this->deposit(100);
        $this->assertEquals(320, $user->refresh()->balance);
        $this->assertEquals(5, $user->points);
        $this->assertEquals(75, $user->getAttribute('demo_balance'));
        $this->assertEquals(10, $user->getAttribute('demo_points'));
    }

    public function test_local_manager_counts_paid_test_session_and_exports_its_origin(): void
    {
        $charger = $this->charger();
        $this->actingAs(User::factory()->create(['balance' => 100]));
        $session = $this->start($this->reserve($charger));
        $this->travel(1)->hour();
        $this->postJson(route('charging-sessions.end-test', $session))->assertOk();
        $this->postJson(route('charging-sessions.pay', $session), ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->actingAs($charger->establishment->user)->get(route('manager.dashboard'))->assertOk()
            ->assertViewHas('totalRevenue', fn ($amount) => (float) $amount === 57.0)
            ->assertViewHas('totalSessions', 1)->assertViewHas('totalEnergy', fn ($energy) => (float) $energy === 30.0)
            ->assertViewHas('periodRevenue', fn ($amount) => (float) $amount === 57.0)
            ->assertViewHas('dailyRevenue', fn ($amounts) => (float) $amounts->sum() === 57.0)
            ->assertViewHas('recentSessions', fn ($sessions) => $sessions->contains('id', $session->id))
            ->assertSee($charger->name)->assertSee('Recarga de teste');
        $export = $this->get(route('manager.dashboard.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString($charger->name, $export);
        $this->assertStringContainsString('Recarga de teste', $export);

        $this->app->instance('env', 'production');
        $this->get(route('manager.dashboard'))->assertOk()->assertViewHas('totalRevenue', 0)
            ->assertViewHas('totalSessions', 0)->assertViewHas('totalEnergy', 0)
            ->assertSee('Recarga de teste');
        $productionExport = $this->get(route('manager.dashboard.export'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($charger->name, $productionExport);
    }

    public function test_local_manager_lists_and_counts_only_own_real_and_test_sessions(): void
    {
        $charger = $this->charger();
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);
        $testSession = $this->start($this->reserve($charger));
        $transaction = $user->transactions()->create(['type' => 'charge_payment', 'amount' => 19, 'status' => 'approved', 'description' => 'Pagamento da sessão regular']);
        $realSession = ChargingSession::factory()->for($user)->create([
            'charger_id' => $charger->id, 'establishment_id' => $charger->establishment_id,
            'charger_name' => 'Sessão real do estabelecimento', 'status' => 'completed', 'payment_status' => 'paid',
            'energy_kwh' => 10, 'measured_energy_kwh' => '10.0000', 'tariff_per_kwh' => '1.9000',
            'amount_charged' => 19, 'original_amount' => 19, 'transaction_id' => $transaction->id,
        ]);
        ChargingSession::factory()->create(['charger_name' => 'TESTE-PRIVADO-OUTRA-LOJA', 'simulation' => ['speed' => 1]]);

        $this->actingAs($charger->establishment->user)->get(route('manager.dashboard'))->assertOk()
            ->assertViewHas('totalRevenue', fn ($amount) => (float) $amount === 19.0)
            ->assertViewHas('totalEnergy', fn ($energy) => (float) $energy === 10.0)
            ->assertViewHas('totalSessions', 2)
            ->assertViewHas('recentSessions', fn ($sessions) => $sessions->count() === 2 && $sessions->contains('id', $testSession->id) && $sessions->contains('id', $realSession->id))
            ->assertSee('Recarga de teste')->assertSee('Sessão real do estabelecimento')->assertDontSee('TESTE-PRIVADO-OUTRA-LOJA');
    }
}
