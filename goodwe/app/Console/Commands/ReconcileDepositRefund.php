<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Console\Command;

class ReconcileDepositRefund extends Command
{
    protected $signature = 'wallet:reconcile-refund {transaction : ID do depósito com estorno pendente}';

    protected $description = 'Reprocessa manualmente um estorno registrado quando há saldo suficiente, sem gerar saldo negativo';

    public function handle(WalletService $wallet): int
    {
        $deposit = Transaction::find($this->argument('transaction'));
        if (! $deposit || ! $deposit->refund_review_required) {
            $this->error('Depósito sem conciliação pendente.');

            return self::FAILURE;
        }
        $wallet->refundDeposit($deposit, $deposit->refund_requested_amount, true);
        if ($deposit->refresh()->refund_review_required) {
            $this->error('Saldo insuficiente. Pendência preservada para decisão da equipe financeira.');

            return self::FAILURE;
        }
        $this->info('Estorno conciliado; dinheiro e pontos revertidos com histórico.');

        return self::SUCCESS;
    }
}
