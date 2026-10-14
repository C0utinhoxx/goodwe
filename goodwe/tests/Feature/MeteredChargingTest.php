<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\ChargingSession;
use App\Models\Coupon;
use App\Models\User;
use App\Services\EnergyBilling;
use App\Services\MeteredChargingService;
use App\Services\ReservationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeteredChargingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_start_uses_reserved_tariff_and_never_debits_wallet_early(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 12]);
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $charger->establishment->update(['cost_per_kwh' => '2.2000']);
        $iot = app(MeteredChargingService::class);
        $session = $iot->startConfirmed($user, $charger, 'device-session-1', $reservation->id);
        $this->assertSame('1.9000', $session->tariff_per_kwh);
        $this->assertSame('started', $reservation->refresh()->status);
        $this->assertSame('charging', $session->charging_state);
        $this->assertSame('not_due', $session->payment_status);
        $this->assertSame('in_use', $charger->refresh()->status());
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertSame('12.00', $user->points);
        $this->assertDatabaseCount('transactions', 0);
        $same = $iot->startConfirmed($user, $charger, 'device-session-1', $reservation->id);
        $this->assertSame($session->id, $same->id);
        $this->travel(20)->minutes();
        $this->assertFalse(app(ReservationService::class)->expire($reservation));
        $this->assertDatabaseCount('point_movements', 0);
        $charger->establishment->update(['cost_per_kwh' => '3.0000']);
        $ended = $iot->endConfirmed($session, '30.0000', 'end-1');
        $this->assertSame('57.00', $ended->original_amount);
        $this->assertSame('pending', $ended->payment_status);
        $this->assertSame('100.00', $user->refresh()->balance);
    }

    public function test_start_without_reservation_captures_current_tariff_with_full_precision(): void
    {
        $charger = Charger::factory()->create();
        $charger->establishment->update(['cost_per_kwh' => '2.1234']);
        $user = User::factory()->create(['balance' => 100]);
        $session = app(MeteredChargingService::class)->startConfirmed($user, $charger, 'direct-1');
        $this->assertSame('2.1234', $session->tariff_per_kwh);
        $charger->establishment->update(['cost_per_kwh' => '5.0000']);
        $ended = app(MeteredChargingService::class)->endConfirmed($session, '10.0000', 'end');
        $this->assertSame('21.23', $ended->original_amount);
        $this->assertSame('2.1234', $ended->tariff_per_kwh);
        $this->assertSame('2.90', EnergyBilling::amount('1.2345', '2.3456'));
    }

    public function test_cancelled_or_expired_reservation_cannot_retain_guarantee(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        app(ReservationService::class)->cancel($user, $reservation);
        $charger->establishment->update(['cost_per_kwh' => '2.2000']);
        try {
            app(MeteredChargingService::class)->startConfirmed($user, $charger, 'cancelled-start', $reservation->id);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reservation', $e->errors());
        }
        $new = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $this->travel(16)->minutes();
        try {
            app(MeteredChargingService::class)->startConfirmed($user, $charger, 'late-start', $new->id);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame('expired', $new->refresh()->status);
        }
        $session = app(MeteredChargingService::class)->startConfirmed($user, $charger, 'new-direct');
        $this->assertSame('2.2000', $session->tariff_per_kwh);
        $this->assertDatabaseCount('charging_sessions', 1);
    }

    public function test_zero_cash_prevents_start_even_with_points_and_coupons(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 100]);
        Coupon::factory()->for($user)->create();
        $charger = Charger::factory()->create();
        try {
            app(MeteredChargingService::class)->startConfirmed($user, $charger, 'no-cash');
            $this->fail();
        } catch (InsufficientBalanceException $e) {
            $this->assertDatabaseCount('charging_sessions', 0);
        }
        $this->assertSame('available', $charger->refresh()->status());
    }

    public function test_consumption_is_monotonic_idempotent_and_does_not_truncate_excess_or_assume_stop(): void
    {
        $user = User::factory()->create(['balance' => 10]);
        $charger = Charger::factory()->create();
        $iot = app(MeteredChargingService::class);
        $session = $iot->startConfirmed($user, $charger, 'metered-1');
        $iot->recordConsumption($session, '2.0000', 1, 'meter-1');
        $this->assertSame('3.80', $session->refresh()->original_amount);
        $iot->recordConsumption($session, '2.0000', 1, 'meter-1');
        $iot->recordConsumption($session, '7.0000', 3, 'meter-3');
        $this->assertSame('7.0000', $session->refresh()->measured_energy_kwh);
        $this->assertSame('13.30', $session->original_amount);
        $this->assertTrue($session->balance_exceeded);
        $this->assertSame('stop_requested', $session->charging_state);
        $this->assertNotNull($session->stop_requested_at);
        $this->assertNull($session->stop_confirmed_at);
        $this->assertSame('10.00', $user->refresh()->balance);
        $iot->recordConsumption($session, '1.0000', 2, 'old-meter');
        $this->assertSame('7.0000', $session->refresh()->measured_energy_kwh);
        $this->assertEquals(1, ChargingEvent::where('type', 'stop_required')->count());
        $this->assertFalse(ChargingEvent::where('type', 'stop_required')->sole()->metadata['command_dispatched']);
        try {
            $iot->recordConsumption($session, '3.0000', 3, 'meter-3');
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('event', $e->errors());
        }
        try {
            $iot->recordConsumption($session, '1.0000', 4, 'decreasing');
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('energy', $e->errors());
        }
        $iot->endConfirmed($session, '7.5000', 'end-1', true);
        $this->assertNotNull($session->refresh()->stop_confirmed_at);
        $this->assertSame('14.25', $session->original_amount);
        $this->assertSame('pending', $session->payment_status);
        $this->assertSame('in_use', $charger->refresh()->status());
        $iot->physicalStatusConfirmed($charger, 'available', 'unplugged');
        $this->assertSame('available', $charger->refresh()->status());
    }

    public function test_session_payment_with_silver_coupon_is_atomic_and_does_not_use_points_again(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 8.55]);
        $session = ChargingSession::factory()->for($user)->create();
        $coupon = Coupon::factory()->for($user)->create();
        $key = (string) Str::uuid();
        $this->actingAs($user)->get(route('charging-sessions.show', $session))->assertOk()->assertSee('30,0000')->assertSee('1,9000')->assertSee('57,00')->assertSee($coupon->code)->assertSee('pagamento pendente');
        $payload = ['coupon_id' => $coupon->id, 'idempotency_key' => $key, 'amount' => 0.01, 'tariff_per_kwh' => 0.01, 'discount_percent' => 100, 'energy_kwh' => 0.01];
        $this->post(route('charging-sessions.pay', $session), $payload)->assertRedirect(route('charging-sessions.show', $session))->assertSessionHas('success');
        $this->post(route('charging-sessions.pay', $session), $payload)->assertSessionHas('success');
        $this->assertSame('57.00', $session->refresh()->original_amount);
        $this->assertSame('5.70', $session->discount_amount);
        $this->assertSame('51.30', $session->amount_charged);
        $this->assertSame('paid', $session->payment_status);
        $this->assertSame('completed', $session->status);
        $this->assertNotNull($session->paid_at);
        $this->assertSame('48.70', $user->refresh()->balance);
        $this->assertSame('8.55', $user->points);
        $this->assertSame('used', $coupon->refresh()->status);
        $this->assertSame('51.30', $session->transaction->amount);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('point_movements', 0);
        $this->get(route('wallet.history'))->assertSee('Prata')->assertSee('51,30')->assertSee('Pago')->assertSee('1,9000');
        $this->get(route('charging-sessions.show', $session))->assertSee('Pagamento confirmado')->assertDontSee('Confirmar pagamento');
    }

    public function test_session_payment_without_coupon_uses_snapshot_and_actual_final_energy(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $session = ChargingSession::factory()->for($user)->create();
        $session->establishment->update(['cost_per_kwh' => 9]);
        $this->actingAs($user, 'sanctum')->postJson('/api/sessions/'.$session->id.'/pay', ['idempotency_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('session.amount_charged', '57.00');
        $this->assertSame('43.00', $user->refresh()->balance);
        $this->assertNull($session->refresh()->coupon_id);
    }

    public function test_insufficient_cash_storage_failure_and_invalid_coupon_keep_payment_pending(): void
    {
        $user = User::factory()->create(['balance' => 1]);
        $session = ChargingSession::factory()->for($user)->create();
        $coupon = Coupon::factory()->for($user)->create();
        $wallet = app(WalletService::class);
        try {
            $wallet->paySession($user, $session, $coupon->id, (string) Str::uuid());
            $this->fail();
        } catch (InsufficientBalanceException $e) {
            $this->assertSame('pending', $session->refresh()->payment_status);
        }
        $this->assertSame('available', $coupon->refresh()->status);
        $user->update(['balance' => 100]);
        ChargingEvent::creating(function ($event) {
            if ($event->type === 'paid') {
                throw new \RuntimeException('Storage failed');
            }
        });
        try {
            $wallet->paySession($user, $session, $coupon->id, (string) Str::uuid());
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Storage failed', $e->getMessage());
        } finally {
            ChargingEvent::flushEventListeners();
        }
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertSame('available', $coupon->refresh()->status);
        $this->assertSame('pending', $session->refresh()->payment_status);
        $this->assertNull($session->transaction_id);
        $this->assertDatabaseCount('transactions', 0);
        foreach ([Coupon::factory()->create(), Coupon::factory()->for($user)->create(['status' => 'used'])] as $invalid) {
            try {
                $wallet->paySession($user, $session, $invalid->id, (string) Str::uuid());
                $this->fail();
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('coupon_id', $e->errors());
            }
        }
    }

    public function test_payment_requires_owner_and_confirmed_end_and_zero_consumption_is_safe(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $other = User::factory()->create();
        $session = ChargingSession::factory()->for($user)->create(['charging_state' => 'charging', 'payment_status' => 'not_due', 'ended_at' => null]);
        $this->actingAs($other)->get(route('charging-sessions.show', $session))->assertForbidden();
        $this->postJson(route('charging-sessions.pay', $session), ['idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($user)->postJson(route('charging-sessions.pay', $session), ['idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $session->update(['charging_state' => 'ended', 'payment_status' => 'pending', 'ended_at' => now(), 'measured_energy_kwh' => '0.0000']);
        $this->postJson(route('charging-sessions.pay', $session), ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->assertSame('0.00', $session->refresh()->amount_charged);
        $this->assertSame('100.00', $user->refresh()->balance);
    }

    public function test_authorization_precheck_requires_cash_without_starting_session_or_holding_money(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 50]);
        $charger = Charger::factory()->create();
        $reservation = app(ReservationService::class)->reserve($user, $charger, (string) Str::uuid());
        $service = app(MeteredChargingService::class);
        try {
            $service->assertCanAuthorize($user, $charger, $reservation->id);
            $this->fail();
        } catch (InsufficientBalanceException $e) {
            $this->assertDatabaseCount('charging_sessions', 0);
        }
        $user->update(['balance' => 10]);
        $service->assertCanAuthorize($user, $charger, $reservation->id);
        $this->assertSame('10.00', $user->refresh()->balance);
        $this->assertSame('50.00', $user->points);
        $this->assertSame('active', $reservation->refresh()->status);
        $this->assertSame('reserved', $charger->refresh()->status());
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->assertDatabaseCount('transactions', 0);
    }
}
