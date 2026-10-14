<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\ChargingEvent;
use App\Models\ChargingSession;
use App\Models\Coupon;
use App\Models\PointMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(private LoyaltyService $loyalty) {}

    public function deposit(User $user, float $amount): Transaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Valor inválido.']);
        }

        return DB::transaction(function () use ($user, $amount) {
            $deposit = Transaction::create(['user_id' => $user->id, 'type' => Transaction::TYPE_DEPOSIT, 'amount' => DecimalAmount::decimal(DecimalAmount::units($amount)), 'description' => "Depósito de R$ {$amount}", 'status' => Transaction::STATUS_PENDING]);
            $this->creditApprovedDeposit($deposit);

            return $deposit->refresh();
        }, 5);
    }

    public function creditApprovedDeposit(Transaction $transaction, ?string $paymentId = null): void
    {
        DB::transaction(function () use ($transaction, $paymentId) {
            $user = UserBalanceLock::acquire($transaction->user_id);
            $deposit = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($paymentId && $deposit->gateway_payment_id && $deposit->gateway_payment_id !== $paymentId) {
                throw ValidationException::withMessages(['payment' => 'Depósito associado a outro pagamento.']);
            }
            if ($paymentId && $deposit->credited_at && ! $deposit->gateway_payment_id) {
                $deposit->update(['gateway_payment_id' => $paymentId]);
            }
            if ($deposit->type !== Transaction::TYPE_DEPOSIT || $deposit->credited_at || $deposit->status !== Transaction::STATUS_PENDING) {
                return;
            }
            $amount = DecimalAmount::units($deposit->amount);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Valor de depósito inválido.']);
            }
            $user->update(['balance' => DecimalAmount::decimal(DecimalAmount::units($user->balance) + $amount)]);
            $this->loyalty->awardDeposit($user, $deposit);
            $deposit->update(['status' => Transaction::STATUS_APPROVED, 'credited_at' => now(), 'gateway_payment_id' => $paymentId]);
        }, 5);
    }

    public function refundDeposit(Transaction $transaction, string|float $refundedAmount, bool $manual = false, ?string $paymentId = null): void
    {
        DB::transaction(function () use ($transaction, $refundedAmount, $manual, $paymentId) {
            $user = UserBalanceLock::acquire($transaction->user_id);
            $deposit = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($paymentId && $deposit->gateway_payment_id && $deposit->gateway_payment_id !== $paymentId) {
                throw ValidationException::withMessages(['payment' => 'Estorno associado a outro pagamento.']);
            }
            if (! $deposit->credited_at || $deposit->type !== Transaction::TYPE_DEPOSIT) {
                return;
            }
            $requested = max(DecimalAmount::units($deposit->refund_requested_amount), min(DecimalAmount::units($deposit->amount), DecimalAmount::units($refundedAmount)));
            $old = DecimalAmount::units($deposit->refunded_amount);
            if ($requested <= $old) {
                return;
            }
            $deposit->update(['refund_requested_amount' => DecimalAmount::decimal($requested)]);
            $money = $requested - $old;
            $points = DecimalAmount::percentage($requested, config('loyalty.deposit_percent')) - DecimalAmount::percentage($old, config('loyalty.deposit_percent'));
            if ((! $manual && $deposit->refund_review_required) || DecimalAmount::units($user->balance) < $money || DecimalAmount::units($user->points) < $points) {
                $deposit->update(['refund_review_required' => true]);
                PointMovement::firstOrCreate(['event_key' => "refund-review:{$deposit->id}:{$requested}"], ['user_id' => $user->id, 'transaction_id' => $deposit->id, 'type' => 'adjustment', 'amount' => '0.00', 'balance_after' => $user->points, 'description' => 'Estorno recebido: conciliação manual necessária, saldos preservados', 'metadata' => ['refund_requested' => DecimalAmount::decimal($requested), 'pending_points_reversal' => DecimalAmount::decimal($points)]]);

                return;
            }
            $user->update(['balance' => DecimalAmount::decimal(DecimalAmount::units($user->balance) - $money), 'points' => DecimalAmount::decimal(DecimalAmount::units($user->points) - $points)]);
            PointMovement::create(['user_id' => $user->id, 'transaction_id' => $deposit->id, 'type' => 'refund', 'amount' => DecimalAmount::decimal(-$points), 'balance_after' => $user->points, 'description' => 'Reversão de pontos por estorno de depósito', 'event_key' => "refund:{$deposit->id}:{$requested}", 'metadata' => ['refunded_money' => DecimalAmount::decimal($money)]]);
            $deposit->update(['refunded_amount' => DecimalAmount::decimal($requested), 'refund_review_required' => false]);
        }, 5);
    }

    public function payCharging(User $user, float $amount, string $chargerName, float $energyKwh, ?int $couponId, string $key): ChargingSession
    {
        $original = DecimalAmount::units($amount);
        if ($original <= 0 || $energyKwh <= 0) {
            throw ValidationException::withMessages(['amount' => 'Valor e energia devem ser positivos.']);
        }

        return DB::transaction(function () use ($user, $original, $chargerName, $energyKwh, $couponId, $key) {
            $locked = UserBalanceLock::acquire($user->id);
            if ($key && ($existing = $locked->chargingSessions()->where('idempotency_key', $key)->first())) {
                if ($existing->charger_name !== $chargerName || DecimalAmount::units($existing->original_amount) !== $original || DecimalAmount::units($existing->energy_kwh) !== DecimalAmount::units($energyKwh) || $existing->coupon_id !== $couponId) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Identificador já utilizado para outro pagamento.']);
                }

                return $existing;
            }
            $coupon = $this->paymentCoupon($locked, $couponId);
            $discount = $coupon ? min($original, DecimalAmount::percentage($original, $coupon->discount_percent)) : 0;
            $final = $original - $discount;
            if (! User::whereKey($locked->id)->where('balance', '>=', DecimalAmount::decimal($final))->decrement('balance', DecimalAmount::decimal($final))) {
                throw new InsufficientBalanceException('Saldo insuficiente para realizar a recarga.');
            }
            $transaction = Transaction::create(['user_id' => $locked->id, 'type' => Transaction::TYPE_CHARGE_PAYMENT, 'amount' => DecimalAmount::decimal($final), 'description' => "Pagamento de recarga - {$chargerName}", 'status' => Transaction::STATUS_APPROVED]);
            $session = ChargingSession::create(['user_id' => $locked->id, 'charger_name' => $chargerName, 'energy_kwh' => $energyKwh, 'original_amount' => DecimalAmount::decimal($original), 'discount_amount' => DecimalAmount::decimal($discount), 'amount_charged' => DecimalAmount::decimal($final), 'coupon_id' => $coupon?->id, 'transaction_id' => $transaction->id, 'idempotency_key' => $key, 'payment_method' => ChargingSession::PAYMENT_WALLET, 'status' => ChargingSession::STATUS_COMPLETED]);
            $coupon?->update(['status' => 'used', 'used_at' => now()]);

            return $session;
        }, 5);
    }

    public function paySession(User $user, ChargingSession $session, ?int $couponId, string $key): ChargingSession
    {
        abort_unless($session->user_id === $user->id, 403);

        return DB::transaction(function () use ($user, $session, $couponId, $key) {
            $lockedUser = UserBalanceLock::acquire($user->id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status === 'paid') {
                if ($locked->coupon_id !== $couponId) {
                    throw ValidationException::withMessages(['coupon_id' => 'Pagamento já confirmado com outra seleção de cupom.']);
                }

                return $locked;
            }
            if ($locked->charging_state !== 'ended' || $locked->payment_status !== 'pending' || ! $locked->ended_at || $locked->measured_energy_kwh === null || $locked->tariff_per_kwh === null || $locked->transaction_id) {
                throw ValidationException::withMessages(['session' => 'O pagamento depende da confirmação de encerramento e consumo do equipamento.']);
            }
            if ($lockedUser->chargingSessions()->where('idempotency_key', $key)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['idempotency_key' => 'Identificador já usado em outro pagamento.']);
            }
            $original = DecimalAmount::units(EnergyBilling::amount($locked->measured_energy_kwh, $locked->tariff_per_kwh));
            $coupon = $this->paymentCoupon($lockedUser, $couponId);
            $discount = $coupon ? min($original, DecimalAmount::percentage($original, $coupon->discount_percent)) : 0;
            $final = $original - $discount;
            if ($final > 0 && ! User::whereKey($user->id)->where('balance', '>=', DecimalAmount::decimal($final))->decrement('balance', DecimalAmount::decimal($final))) {
                throw new InsufficientBalanceException('Saldo insuficiente para confirmar o pagamento. Adicione créditos à carteira.');
            }
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_CHARGE_PAYMENT,
                'amount' => DecimalAmount::decimal($final),
                'description' => 'Pagamento da sessão #'.$locked->id.' — '.$locked->charger_name,
                'status' => Transaction::STATUS_APPROVED,
            ]);
            $locked->update([
                'original_amount' => DecimalAmount::decimal($original),
                'discount_amount' => DecimalAmount::decimal($discount),
                'amount_charged' => DecimalAmount::decimal($final),
                'coupon_id' => $coupon?->id,
                'transaction_id' => $transaction->id,
                'idempotency_key' => $key,
                'payment_status' => 'paid',
                'status' => ChargingSession::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);
            $coupon?->update(['status' => 'used', 'used_at' => now()]);
            ChargingEvent::create(['charger_id' => $locked->charger_id, 'charging_session_id' => $locked->id, 'charging_reservation_id' => $locked->charging_reservation_id, 'type' => 'paid', 'event_key' => 'session:'.$locked->id.':paid', 'metadata' => ['transaction_id' => $transaction->id, 'coupon_id' => $coupon?->id, 'amount' => DecimalAmount::decimal($final)], 'occurred_at' => now()]);

            return $locked;
        }, 5);
    }

    private function paymentCoupon(User $user, ?int $couponId): ?Coupon
    {
        $coupon = $couponId ? $user->coupons()->whereKey($couponId)->lockForUpdate()->first() : null;
        if ($couponId && (! $coupon || $coupon->status !== 'available')) {
            throw ValidationException::withMessages(['coupon_id' => 'Cupom inválido, utilizado ou pertencente a outra conta.']);
        }

        return $coupon;
    }

    public function getHistory(User $user): Collection
    {
        return $user->transactions()->latest()->get();
    }

    public function getRecentSessions(User $user): Collection
    {
        return $user->chargingSessions()->latest()->take(5)->get();
    }
}
