<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
    }

    public function test_webhook_credits_wallet_when_payment_is_approved(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 0]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 100,
            'description' => 'Deposito via MercadoPago de R$ 100',
            'reference' => 'pref-123',
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->gateway->willReturn('payment-1', 'approved', (string) $transaction->id);

        $response = $this->postJson(route('mercadopago.webhook'), [
            'data' => ['id' => 'payment-1'],
        ]);

        $response->assertNoContent();

        $user->refresh();
        $this->assertEquals(100, (float) $user->balance);
        $this->assertEquals(5, $user->points);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => Transaction::STATUS_APPROVED,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'user_id' => $user->id, 'transaction_id' => $transaction->id, 'type' => 'deposit', 'amount' => 5,
        ]);
    }

    public function test_webhook_marks_transaction_rejected_without_crediting_balance(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 0]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 100,
            'description' => 'Deposito via MercadoPago de R$ 100',
            'reference' => 'pref-123',
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->gateway->willReturn('payment-1', 'rejected', (string) $transaction->id);

        $response = $this->postJson(route('mercadopago.webhook'), [
            'data' => ['id' => 'payment-1'],
        ]);

        $response->assertNoContent();

        $user->refresh();
        $this->assertEquals(0, (float) $user->balance);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => Transaction::STATUS_REJECTED,
        ]);
    }

    public function test_webhook_is_idempotent_for_already_processed_transactions(): void
    {
        $user = User::factory()->create(['balance' => 0, 'points' => 0]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 100,
            'description' => 'Deposito via MercadoPago de R$ 100',
            'reference' => 'pref-123',
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->gateway->willReturn('payment-1', 'approved', (string) $transaction->id);

        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'payment-1']])
            ->assertNoContent();
        $this->postJson(route('mercadopago.webhook'), ['data' => ['id' => 'payment-1']])
            ->assertNoContent();

        $user->refresh();
        $this->assertEquals(100, (float) $user->balance);
        $this->assertEquals(5, $user->points);
    }

    public function test_webhook_is_accessible_without_authentication(): void
    {
        $response = $this->postJson(route('mercadopago.webhook'), [
            'data' => ['id' => 'unknown-payment'],
        ]);

        $response->assertNoContent();
    }
}
