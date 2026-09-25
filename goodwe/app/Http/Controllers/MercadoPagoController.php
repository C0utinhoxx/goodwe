<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Transaction;
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

        if (! $transaction || $transaction->status !== Transaction::STATUS_PENDING) {
            return response()->noContent();
        }

        if ($payment['status'] === 'approved') {
            $transaction->update(['status' => Transaction::STATUS_APPROVED]);
            $this->walletService->creditApprovedDeposit($transaction);
        } elseif (in_array($payment['status'], ['rejected', 'cancelled'], true)) {
            $transaction->update(['status' => Transaction::STATUS_REJECTED]);
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
