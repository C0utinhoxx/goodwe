<?php

namespace Tests\Fakes;

use App\Contracts\PaymentGatewayInterface;
use App\Models\User;

class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var array<string, array{status: string, external_reference: ?string}> */
    public array $payments = [];

    public string $preferenceId = 'pref-fake-123';

    public string $initPoint = 'https://sandbox.mercadopago.com/checkout/fake';

    public function createPreference(User $user, float $amount, string $externalReference): array
    {
        return [
            'id' => $this->preferenceId,
            'init_point' => $this->initPoint,
        ];
    }

    public function findPayment(string $paymentId): array
    {
        return $this->payments[$paymentId] ?? [
            'id' => $paymentId,
            'status' => 'pending',
            'external_reference' => null,
        ];
    }

    public function willReturn(string $paymentId, string $status, ?string $externalReference): void
    {
        $this->payments[$paymentId] = [
            'id' => $paymentId,
            'status' => $status,
            'external_reference' => $externalReference,
        ];
    }
}
