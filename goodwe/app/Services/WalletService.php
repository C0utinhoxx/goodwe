<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientPointsException;
use App\Models\ChargingSession;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    private const CASHBACK_RATE = 0.10;

    public function deposit(User $user, float $amount): Transaction
    {
        return DB::transaction(function () use ($user, $amount) {
            $user->increment('balance', $amount);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'description' => "Deposito de R$ {$amount}",
            ]);

            $cashbackAmount = round($amount * self::CASHBACK_RATE, 2);

            if ($cashbackAmount > 0) {
                $points = (int) ($cashbackAmount * 100);
                $user->increment('points', $points);

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => Transaction::TYPE_CASHBACK,
                    'amount' => $cashbackAmount,
                    'description' => "Cashback de 10% - {$points} pontos",
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Credit a deposit that was pending confirmation from an external payment
     * gateway (e.g. MercadoPago), applying the same cashback rules used for
     * instant deposits.
     */
    public function creditApprovedDeposit(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $user = $transaction->user;

            $user->increment('balance', $transaction->amount);

            $cashbackAmount = round($transaction->amount * self::CASHBACK_RATE, 2);

            if ($cashbackAmount > 0) {
                $points = (int) ($cashbackAmount * 100);
                $user->increment('points', $points);

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => Transaction::TYPE_CASHBACK,
                    'amount' => $cashbackAmount,
                    'description' => "Cashback de 10% - {$points} pontos",
                    'reference' => $transaction->reference,
                    'status' => Transaction::STATUS_APPROVED,
                ]);
            }
        });
    }

    public function payCharging(User $user, float $amount, string $chargerName, float $energyKwh): ChargingSession
    {
        return DB::transaction(function () use ($user, $amount, $chargerName, $energyKwh) {
            if ($user->balance < $amount) {
                throw new InsufficientBalanceException('Saldo insuficiente para realizar a recarga.');
            }

            $user->decrement('balance', $amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_CHARGE_PAYMENT,
                'amount' => $amount,
                'description' => "Pagamento de recarga - {$chargerName}",
            ]);

            $cashbackAmount = round($amount * self::CASHBACK_RATE, 2);

            if ($cashbackAmount > 0) {
                $points = (int) ($cashbackAmount * 100);
                $user->increment('points', $points);

                Transaction::create([
                    'user_id' => $user->id,
                    'type' => Transaction::TYPE_CASHBACK,
                    'amount' => $cashbackAmount,
                    'description' => "Cashback de 10% - {$points} pontos",
                ]);
            }

            return ChargingSession::create([
                'user_id' => $user->id,
                'charger_name' => $chargerName,
                'energy_kwh' => $energyKwh,
                'amount_charged' => $amount,
                'payment_method' => ChargingSession::PAYMENT_WALLET,
                'status' => ChargingSession::STATUS_COMPLETED,
            ]);
        });
    }

    public function redeemPoints(User $user, int $points): Transaction
    {
        return DB::transaction(function () use ($user, $points) {
            if ($user->points < $points) {
                throw new InsufficientPointsException('Pontos insuficientes para resgate.');
            }

            $creditAmount = $points / 100;

            $user->decrement('points', $points);
            $user->increment('balance', $creditAmount);

            return Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_POINTS_REDEEM,
                'amount' => $creditAmount,
                'description' => "Resgate de {$points} pontos - R$ {$creditAmount}",
            ]);
        });
    }

    public function getHistory(User $user)
    {
        return $user->transactions()->latest()->get();
    }

    public function getRecentSessions(User $user)
    {
        return $user->chargingSessions()->latest()->take(5)->get();
    }
}
