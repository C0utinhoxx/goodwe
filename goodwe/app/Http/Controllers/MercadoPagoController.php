<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Transaction;
use App\Services\DecimalAmount;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MercadoPagoController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly WalletService $walletService,
    ) {}

    public function checkout(StoreCheckoutRequest $request)
    {
        $amount = round((float) $request->validated('amount'), 2);
        $user = $request->user();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => "Deposito via MercadoPago de R$ {$amount}",
            'status' => Transaction::STATUS_PENDING,
        ]);

        $preference = $this->gateway->createPreference($user, $amount, (string) $transaction->id);

        $transaction->update(['reference' => $preference['id']]);

        return redirect()->to($preference['init_point']);
    }

    public function webhook(Request $request): Response
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

    public function sucesso(Request $request): View
    {
        return view('pagamento.sucesso');
    }

    public function erro(Request $request): View
    {
        return view('pagamento.erro');
    }

    public function pendente(Request $request): View
    {
        return view('pagamento.pendente');
    }
}
