<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\User;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;

class MercadoPagoGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PreferenceClient $preferenceClient = new PreferenceClient,
        private readonly PaymentClient $paymentClient = new PaymentClient,
    ) {}

    public function createPreference(User $user, float $amount, string $externalReference): array
    {
        $preference = $this->preferenceClient->create([
            'items' => [
                [
                    'id' => "deposito-{$externalReference}",
                    'title' => 'Deposito na carteira GoodWe',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            'payer' => [
                'email' => $user->email,
            ],
            'external_reference' => $externalReference,
            'back_urls' => [
                'success' => route('pagamento.sucesso'),
                'failure' => route('pagamento.erro'),
                'pending' => route('pagamento.pendente'),
            ],
            'auto_return' => 'approved',
            'notification_url' => route('mercadopago.webhook'),
        ]);

        return [
            'id' => (string) $preference->id,
            'init_point' => (string) $preference->init_point,
        ];
    }

    public function findPayment(string $paymentId): array
    {
        $payment = $this->paymentClient->get((int) $paymentId);

        return [
            'id' => (string) $payment->id,
            'status' => (string) $payment->status,
            'external_reference' => $payment->external_reference,
            'transaction_amount' => (string) $payment->transaction_amount,
            'transaction_amount_refunded' => (string) ($payment->transaction_amount_refunded ?? 0),
            'currency_id' => $payment->currency_id,
        ];
    }
}
