<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use App\Services\DecimalAmount;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Processa os webhooks de pagamentos ja initiados antes da migracao para a
 * Stripe. O checkout novo nao usa mais o MercadoPago, mas pedidos antigos
 * ainda podem ser confirmados por este endpoint.
 */
class LegacyPaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly WalletService $walletService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $paymentId = $request->input('data.id') ?? $request->query('id');

        if (! $paymentId) {
            return response()->noContent();
        }

        $payment = $this->gateway->findPayment((string) $paymentId);

        $transaction = ! empty($payment['external_reference'])
            ? Transaction::find($payment['external_reference'])
            : null;

        if (! $transaction || $transaction->type !== Transaction::TYPE_DEPOSIT) {
            return response()->noContent();
        }
        if (isset($payment['transaction_amount']) && (DecimalAmount::units($payment['transaction_amount']) !== DecimalAmount::units($transaction->amount) || ($payment['currency_id'] ?? 'BRL') !== 'BRL')) {
            return response('Pagamento não corresponde ao depósito.', 409);
        }
        if ($transaction->gateway_payment_id && $transaction->gateway_payment_id !== $payment['id']) {
            return response('Depósito associado a outro pagamento.', 409);
        }
        if ($payment['status'] === 'approved') {
            $this->walletService->creditApprovedDeposit($transaction, $payment['id']);
        } elseif (in_array($payment['status'], ['rejected', 'cancelled'], true)) {
            Transaction::whereKey($transaction->id)->where('status', Transaction::STATUS_PENDING)->whereNull('credited_at')->update(['status' => Transaction::STATUS_REJECTED]);
        }
        $refunded = $payment['transaction_amount_refunded'] ?? ($payment['status'] === 'refunded' ? $transaction->amount : 0);
        if ((float) $refunded > 0) {
            $this->walletService->refundDeposit($transaction, $refunded, false, $payment['id']);
        }

        return response()->noContent();
    }
}