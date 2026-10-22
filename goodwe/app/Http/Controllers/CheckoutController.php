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

    /**
     * Cria a transação pendente e redireciona para o Stripe Checkout
     * (rota `stripe.create`).
     */
    public function stripeCheckout(StoreCheckoutRequest $request)
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
            // {CHECKOUT_SESSION_ID} é trocado pelo Stripe pelo id real da sessão
            'success_url' => route('pagamento.sucesso') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('pagamento.erro'),
        ]);

        $transaction->update(['reference' => $session->id]);

        return redirect($session->url);
    }

    /**
     * Página de retorno do Stripe: confirma o pagamento direto na API do
     * Stripe e credita saldo e pontos, sem depender do webhook.
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if ($sessionId && $request->user()) {
            $this->creditFromSession($sessionId, $request->user()->id);
        }

        return view('pagamento.sucesso');
    }

    /**
     * Consulta a sessão no Stripe e credita se estiver paga.
     * Idempotente: só credita transações ainda pendentes (lockForUpdate).
     */
    private function creditFromSession(string $sessionId, int $userId): void
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::error('Stripe: falha ao consultar sessão', ['erro' => $e->getMessage()]);

            return;
        }

        if ($session->payment_status !== 'paid' || empty($session->client_reference_id)) {
            return;
        }

        DB::transaction(function () use ($session, $userId) {
            $transaction = Transaction::whereKey($session->client_reference_id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $transaction || $transaction->status !== Transaction::STATUS_PENDING) {
                return;
            }

            $esperado = (int) round($transaction->amount * 100);

            if ((int) $session->amount_total !== $esperado) {
                Log::warning('Stripe: valor divergente (success)', [
                    'transaction_id' => $transaction->id,
                    'esperado' => $esperado,
                    'recebido' => $session->amount_total,
                ]);

                return;
            }

            // creditApprovedDeposit só credita transações PENDING e já marca a
            // transação como aprovada. Não altere o status antes de chamá-lo.
            $this->walletService->creditApprovedDeposit(
                $transaction,
                is_string($session->payment_intent ?? null) ? $session->payment_intent : null
            );
        });
    }

    public function cancel()
    {
        return view('pagamento.erro');
    }

    public function pending()
    {
        return view('pagamento.pendente');
    }

    /**
     * Webhook do Stripe: garantia caso o cliente feche o navegador antes de
     * voltar ao site, e necessário para pagamentos assíncronos (Pix).
     */
    public function webhook(Request $request)
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook: assinatura inválida', ['erro' => $e->getMessage()]);

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
            return response('ignorado: ' . $event->type, 200);
        }

        $session = $event->data->object;

        if (empty($session->client_reference_id)) {
            return response('sem client_reference_id', 200);
        }

        $motivo = 'ok';

        DB::transaction(function () use ($event, $session, $succeeded, $failed, &$motivo) {
            // lockForUpdate evita crédito duplicado se o Stripe reenviar o evento
            $transaction = Transaction::whereKey($session->client_reference_id)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                $motivo = 'transacao nao encontrada: ' . $session->client_reference_id;

                return;
            }

            if ($transaction->status !== Transaction::STATUS_PENDING) {
                $motivo = 'status ja era: ' . $transaction->status;

                return;
            }

            if (in_array($event->type, $succeeded, true) && $session->payment_status === 'paid') {
                $esperado = (int) round($transaction->amount * 100);

                if ((int) $session->amount_total !== $esperado) {
                    $motivo = "valor divergente: esperado {$esperado}, recebido {$session->amount_total}";
                    Log::warning('Stripe: valor divergente', [
                        'transaction_id' => $transaction->id,
                        'esperado' => $esperado,
                        'recebido' => $session->amount_total,
                    ]);

                    return;
                }

                // creditApprovedDeposit já marca a transação como aprovada
                $this->walletService->creditApprovedDeposit(
                    $transaction,
                    is_string($session->payment_intent ?? null) ? $session->payment_intent : null
                );
                $motivo = 'creditado';

                return;
            }

            if (in_array($event->type, $failed, true)) {
                $transaction->update(['status' => Transaction::STATUS_REJECTED]);
                $motivo = 'rejeitado';

                return;
            }

            $motivo = 'pagamento nao confirmado: ' . $session->payment_status;
        });

        return response($motivo, 200);
    }
}