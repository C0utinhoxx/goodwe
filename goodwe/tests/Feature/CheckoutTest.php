<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
    }

    public function test_authenticated_user_can_start_checkout_with_valid_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout'), [
            'amount' => 150.50,
        ]);

        $response->assertRedirect($this->gateway->initPoint);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => 150.50,
            'status' => Transaction::STATUS_PENDING,
            'reference' => $this->gateway->preferenceId,
        ]);
    }

    public function test_checkout_requires_amount(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout'), []);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_checkout_rejects_amount_below_minimum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout'), [
            'amount' => 0,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_checkout_rejects_amount_above_maximum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout'), [
            'amount' => 10001,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_guest_cannot_access_checkout(): void
    {
        $response = $this->post(route('checkout'), [
            'amount' => 100,
        ]);

        $response->assertRedirect(route('user.login'));
        $this->assertDatabaseCount('transactions', 0);
    }
}
