<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class CheckoutController extends Controller
{
    public function __construct(
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
            'description' => "Deposito via Stripe de R$ {$amount}",
            'status' => Transaction::STATUS_PENDING,
        ]);

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $user->email,
            'client_reference_id' => (string) $transaction->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'brl',
                    'product_data' => ['name' => 'Depósito na carteira'],
                    'unit_amount' => (int) round($amount * 100),
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('pagamento.sucesso'),
            'cancel_url' => route('pagamento.erro'),
        ]);

        $transaction->update(['reference' => $session->id]);

        return redirect($session->url);
    }

    public function success()
    {
        return view('pagamento.sucesso');
    }

    public function cancel()
    {
        return view('pagamento.erro');
    }

    public function pending()
    {
        return view('pagamento.pendente');
    }

    public function webhook(Request $request)
    {
        Log::info('Stripe webhook recebido');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            return response('Invalid payload or signature', 400);
        }

        $succeeded = [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ];
        $failed = [
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ];

        if (! in_array($event->type, [...$succeeded, ...$failed], true)) {
            return response('', 200);
        }

        $session = $event->data->object;

        if (empty($session->client_reference_id)) {
            return response('', 200);
        }

        DB::transaction(function () use ($event, $session, $succeeded, $failed) {
            // lockForUpdate evita crédito duplicado se o Stripe reenviar o evento
            $transaction = Transaction::whereKey($session->client_reference_id)
                ->lockForUpdate()
                ->first();

            if (! $transaction || $transaction->status !== Transaction::STATUS_PENDING) {
                return;
            }

            if (in_array($event->type, $succeeded, true) && $session->payment_status === 'paid') {
                $esperado = (int) round($transaction->amount * 100);

                if ((int) $session->amount_total !== $esperado) {
                    Log::warning('Stripe: valor divergente', [
                        'transaction_id' => $transaction->id,
                        'esperado' => $esperado,
                        'recebido' => $session->amount_total,
                    ]);

                    return;
                }

                $transaction->update(['status' => Transaction::STATUS_APPROVED]);
                $this->walletService->creditApprovedDeposit($transaction);

                return;
            }

            if (in_array($event->type, $failed, true)) {
                $transaction->update(['status' => Transaction::STATUS_REJECTED]);
            }
        });

        return response('', 200);
    }
}
