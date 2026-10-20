<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\InsufficientBalanceException;
use App\Models\ChargingSession;
use App\Models\Coupon;
use App\Models\User;
use App\Services\ChargingSimulator;
use App\Services\LoyaltyService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    public static function deposits(): array
    {
        return [[100, '5.00'], [85.50, '4.28'], [1.05, '0.05']];
    }

    #[DataProvider('deposits')]
    public function test_confirmed_deposit_awards_decimal_points(float $amount, string $points): void
    {
        $user = User::factory()->create();
        $deposit = app(WalletService::class)->deposit($user, $amount);
        $this->assertEquals($amount, $user->refresh()->balance);
        $this->assertSame($points, $user->points);
        $this->assertDatabaseHas('point_movements', ['transaction_id' => $deposit->id, 'type' => 'deposit', 'amount' => $points]);
        $this->assertNotNull($deposit->credited_at);
    }

    public function test_pending_and_cancelled_deposits_never_award_points(): void
    {
        $user = User::factory()->create();
        $deposit = $user->transactions()->create(['type' => 'deposit', 'amount' => 100, 'description' => 'Depósito de teste', 'status' => 'pending']);
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
        foreach (['pending', 'cancelled'] as $status) {
            $gateway->willReturn('p1', $status, (string) $deposit->id);
            $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'p1']])->assertNoContent();
            $this->assertSame('0.00', $user->refresh()->points);
            $this->assertSame('0.00', $user->balance);
        }
        $this->assertDatabaseCount('point_movements', 0);
    }

    public static function rewards(): array
    {
        return [['bronze', 10, 5], ['prata', 25, 10], ['ouro', 50, 15]];
    }

    #[DataProvider('rewards')]
    public function test_reward_is_available_and_debits_points_once(string $tier, int $cost, int $percent): void
    {
        $user = User::factory()->create(['points' => '60.55', 'balance' => 100]);
        $key = (string) Str::uuid();
        $this->actingAs($user)->post(route('points.redeem'), ['tier' => $tier, 'idempotency_key' => $key])->assertRedirect(route('points.index'))->assertSessionHas('success');
        $this->post(route('points.redeem'), ['tier' => $tier, 'idempotency_key' => $key])->assertRedirect();
        $this->assertSame(number_format(60.55 - $cost, 2, '.', ''), $user->refresh()->points);
        $this->assertSame('100.00', $user->balance);
        $this->assertDatabaseCount('coupons', 1);
        $coupon = $user->coupons()->sole();
        $this->assertSame('available', $coupon->status);
        $this->assertSame($percent, $coupon->discount_percent);
        $this->assertDatabaseHas('point_movements', ['coupon_id' => $coupon->id, 'amount' => -$cost]);
        $this->get(route('points.index'))->assertOk()->assertSee($coupon->code)->assertSee('Disponível');
    }

    public function test_insufficient_points_cannot_redeem(): void
    {
        $user = User::factory()->create(['points' => 9.99]);
        $this->actingAs($user)->post(route('points.redeem'), ['tier' => 'bronze', 'idempotency_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->assertDatabaseCount('coupons', 0);
        $this->assertSame('9.99', $user->refresh()->points);
        $this->get(route('points.index'))->assertSee('disabled', false)->assertSee('Faltam 0,01 pontos.');
    }

    public function test_silver_coupon_discount_persists_full_payment_and_never_awards_points(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 8.55]);
        $coupon = Coupon::factory()->for($user)->create();
        $session = app(WalletService::class)->payCharging($user, 86.40, 'CG-001', 45.47, $coupon->id, (string) Str::uuid());
        $this->assertSame('86.40', $session->original_amount);
        $this->assertSame('8.64', $session->discount_amount);
        $this->assertSame('77.76', $session->amount_charged);
        $this->assertSame('22.24', $user->refresh()->balance);
        $this->assertSame('8.55', $user->points);
        $this->assertSame('used', $coupon->refresh()->status);
        $this->assertEquals($session->id, $coupon->chargingSession->id);
        $this->assertSame('77.76', $session->transaction->amount);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_used_foreign_and_cancelled_coupons_cannot_be_applied(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        foreach ([Coupon::factory()->for($user)->create(['status' => 'used']), Coupon::factory()->create(), Coupon::factory()->for($user)->create(['status' => 'cancelled'])] as $coupon) {
            try {
                app(WalletService::class)->payCharging($user, 50, 'CG-001', 10, $coupon->id, (string) Str::uuid());
                $this->fail('Invalid coupon accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('coupon_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('charging_sessions', 0);
        $this->assertSame('100.00', $user->refresh()->balance);
    }

    public function test_insufficient_cash_and_storage_failure_leave_coupon_available(): void
    {
        $user = User::factory()->create(['balance' => 1]);
        $coupon = Coupon::factory()->for($user)->create();
        try {
            app(WalletService::class)->payCharging($user, 50, 'CG-001', 10, $coupon->id, (string) Str::uuid());
            $this->fail();
        } catch (InsufficientBalanceException $e) {
            $this->assertSame('available', $coupon->refresh()->status);
        }
        $user->update(['balance' => 100]);
        ChargingSession::creating(function () {
            throw new \RuntimeException('Storage unavailable');
        });
        try {
            app(WalletService::class)->payCharging($user, 50, 'CG-001', 10, $coupon->id, (string) Str::uuid());
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Storage unavailable', $e->getMessage());
        } finally {
            ChargingSession::flushEventListeners();
        }
        $this->assertSame('available', $coupon->refresh()->status);
        $this->assertSame('100.00', $user->refresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('charging_sessions', 0);
    }

    public function test_duplicate_payment_returns_same_session_without_double_debit(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $coupon = Coupon::factory()->for($user)->create();
        $key = (string) Str::uuid();
        $wallet = app(WalletService::class);
        $first = $wallet->payCharging($user, 50, 'CG-001', 10, $coupon->id, $key);
        $second = $wallet->payCharging($user, 50, 'CG-001', 10, $coupon->id, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('55.00', $user->refresh()->balance);
        $this->assertDatabaseCount('charging_sessions', 1);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_web_payment_selects_coupon_and_recalculates_in_server(): void
    {
        ChargingSimulator::release('CG-001');
        $user = User::factory()->create(['balance' => 100]);
        $coupon = Coupon::factory()->for($user)->create();
        $data = ['vehicle_plate' => 'ABC1D23', 'duration_minutes' => 30, 'coupon_id' => $coupon->id, 'idempotency_key' => (string) Str::uuid(), 'amount' => 0.01, 'discount_percent' => 100];
        $this->actingAs($user)->get(route('chargers.index'))->assertDontSee('Aplicar cupom')->assertSee('A reserva é imediata', false);
        $this->post(route('chargers.schedule', 'CG-001'), $data)->assertSessionHas('success');
        $this->post(route('chargers.schedule', 'CG-001'), $data)->assertSessionHas('success');
        $this->assertDatabaseHas('charging_sessions', ['original_amount' => 19.50, 'discount_amount' => 1.95, 'amount_charged' => 17.55, 'coupon_id' => $coupon->id]);
        $this->assertSame('82.45', $user->refresh()->balance);
        $this->assertDatabaseCount('charging_sessions', 1);
        ChargingSimulator::release('CG-001');
    }

    public function test_api_ignores_client_price_and_supports_coupon_and_decimal_points(): void
    {
        ChargingSimulator::release('CG-001');
        $user = User::factory()->create(['balance' => 100, 'points' => 8.55]);
        $coupon = Coupon::factory()->for($user)->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/chargers/CG-001/start', ['energy_kwh' => 10, 'amount' => 0.01, 'charge_type' => 'partial', 'coupon_id' => $coupon->id, 'idempotency_key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('session.original_amount', 19)->assertJsonPath('session.amount_charged', 17.1)->assertJsonPath('user.points', 8.55);
        ChargingSimulator::release('CG-001');
    }

    public function test_refund_reverses_wallet_and_points_idempotently(): void
    {
        $user = User::factory()->create();
        $wallet = app(WalletService::class);
        $deposit = $wallet->deposit($user, 85.50);
        $wallet->refundDeposit($deposit, '20.00');
        $this->assertSame('65.50', $user->refresh()->balance);
        $this->assertSame('3.28', $user->points);
        $wallet->refundDeposit($deposit, '85.50');
        $wallet->refundDeposit($deposit, '85.50');
        $this->assertSame('0.00', $user->refresh()->balance);
        $this->assertSame('0.00', $user->points);
        $this->assertSame('85.50', $deposit->refresh()->refunded_amount);
        $this->assertDatabaseCount('point_movements', 3);
    }

    public function test_spent_refund_is_pending_manual_review_without_negative_balances(): void
    {
        $user = User::factory()->create();
        $wallet = app(WalletService::class);
        $deposit = $wallet->deposit($user, 200);
        app(LoyaltyService::class)->redeem($user, 'bronze', (string) Str::uuid());
        $wallet->refundDeposit($deposit, '200.00');
        $wallet->refundDeposit($deposit, '200.00');
        $this->assertTrue($deposit->refresh()->refund_review_required);
        $this->assertSame('200.00', $user->refresh()->balance);
        $this->assertSame('0.00', $user->points);
        $this->assertSame('0.00', $deposit->refunded_amount);
        $this->assertDatabaseCount('point_movements', 3);
        $this->actingAs($user)->get(route('wallet.history'))->assertSee('aguardando conciliação manual');
        $this->artisan('wallet:reconcile-refund', ['transaction' => $deposit->id])->assertFailed();
    }

    public function test_refund_webhook_and_mismatched_gateway_amount(): void
    {
        $user = User::factory()->create();
        $deposit = $user->transactions()->create(['type' => 'deposit', 'amount' => 100, 'description' => 'Depósito de teste', 'status' => 'pending']);
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
        $gateway->willReturn('p2', 'approved', (string) $deposit->id);
        $gateway->payments['p2']['transaction_amount'] = '50.00';
        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'p2']])->assertStatus(409);
        $this->assertSame('0.00', $user->refresh()->balance);
        $gateway->payments['p2']['transaction_amount'] = '100.00';
        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'p2']])->assertNoContent();
        $gateway->payments['p2']['status'] = 'refunded';
        $gateway->payments['p2']['transaction_amount_refunded'] = '100.00';
        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'p2']])->assertNoContent();
        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'p2']])->assertNoContent();
        $this->assertSame('0.00', $user->refresh()->balance);
        $this->assertSame('0.00', $user->points);
        $this->assertDatabaseCount('point_movements', 2);
    }

    public function test_guests_invalid_tier_nonce_and_multiple_coupons_are_rejected(): void
    {
        $this->post(route('points.redeem'), [])->assertRedirect(route('user.login'));
        $user = User::factory()->create(['balance' => 100, 'points' => 100]);
        $this->actingAs($user)->postJson(route('points.redeem'), ['tier' => 'diamante', 'idempotency_key' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors(['tier', 'idempotency_key']);
        $this->postJson(route('chargers.schedule', 'CG-001'), ['vehicle_plate' => 'ABC1D23', 'duration_minutes' => 30, 'coupon_id' => [1, 2], 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('coupon_id');
        $this->assertDatabaseCount('coupons', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_api_redemption_uses_coupon_policy_instead_of_cash_conversion(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 25]);
        $this->actingAs($user, 'sanctum')->postJson('/api/wallet/redeem-points', ['tier' => 'prata', 'idempotency_key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('coupon.discount_percent', 10)->assertJsonPath('points', '0.00');
        $this->assertSame('100.00', $user->refresh()->balance);
    }

    public function test_out_of_order_refund_notifications_preserve_largest_manual_pending_amount(): void
    {
        $user = User::factory()->create();
        $wallet = app(WalletService::class);
        $deposit = $wallet->deposit($user, 100);
        $user->update(['balance' => 0]);
        $wallet->refundDeposit($deposit, '100.00');
        $wallet->refundDeposit($deposit, '20.00');
        $this->assertSame('100.00', $deposit->refresh()->refund_requested_amount);
        $this->assertTrue($deposit->refund_review_required);
        $this->assertSame('0.00', $user->refresh()->balance);
        $this->assertDatabaseCount('point_movements', 2);
        $user->update(['balance' => 100]);
        $this->artisan('wallet:reconcile-refund', ['transaction' => $deposit->id])->assertSuccessful();
        $this->assertSame('100.00', $deposit->refresh()->refunded_amount);
        $this->assertFalse($deposit->refund_review_required);
        $this->assertSame('0.00', $user->refresh()->balance);
        $this->assertSame('0.00', $user->points);
    }

    public function test_payment_retries_cannot_change_payment_or_coupon_tier(): void
    {
        $user = User::factory()->create(['balance' => 100, 'points' => 60]);
        $key = (string) Str::uuid();
        $coupon = app(LoyaltyService::class)->redeem($user, 'bronze', $key);
        try {
            app(LoyaltyService::class)->redeem($user, 'prata', $key);
            $this->fail('Changed tier accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
        $paymentKey = (string) Str::uuid();
        app(WalletService::class)->payCharging($user, 50, 'CG-001', 10, $coupon->id, $paymentKey);
        foreach ([['amount' => 60, 'key' => $paymentKey], ['amount' => 50, 'key' => (string) Str::uuid()]] as $retry) {
            try {
                app(WalletService::class)->payCharging($user, $retry['amount'], 'CG-001', 10, $coupon->id, $retry['key']);
                $this->fail('Invalid retry accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame('52.50', $user->refresh()->balance);
        $this->assertSame('50.00', $user->points);
        $this->assertDatabaseCount('charging_sessions', 1);
        $this->assertDatabaseCount('coupons', 1);
    }
}
