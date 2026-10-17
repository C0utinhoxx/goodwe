<?php

namespace App\Contracts;

use App\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Create a payment preference/checkout session for the given amount.
     *
     * @return array{id: string, init_point: string}
     */
    public function createPreference(User $user, float $amount, string $externalReference): array;

    /**
     * Fetch a payment by its gateway id, returning normalized data.
     *
     * @return array{id: string, status: string, external_reference: ?string, transaction_amount?: string, transaction_amount_refunded?: string, currency_id?: string}
     */
    public function findPayment(string $paymentId): array;
}
