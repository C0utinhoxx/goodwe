<?php

namespace App\Services;

use App\Exceptions\InsufficientPointsException;
use App\Models\Coupon;
use App\Models\PointMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function awardDeposit(User $lockedUser, Transaction $deposit): void
    {
        $units = DecimalAmount::percentage(DecimalAmount::units($deposit->amount), config('loyalty.deposit_percent'));
        $balance = DecimalAmount::units($lockedUser->points) + $units;
        $lockedUser->update(['points' => DecimalAmount::decimal($balance)]);
        PointMovement::create(['user_id' => $lockedUser->id, 'transaction_id' => $deposit->id, 'type' => 'deposit', 'amount' => DecimalAmount::decimal($units), 'balance_after' => DecimalAmount::decimal($balance), 'description' => 'Pontos por depósito confirmado', 'event_key' => "deposit:{$deposit->id}"]);
    }

    public function redeem(User $user, string $tier, string $key): Coupon
    {
        $benefit = config("loyalty.coupons.{$tier}");
        if (! $benefit) {
            throw ValidationException::withMessages(['tier' => 'Benefício inválido.']);
        }

        return DB::transaction(function () use ($user, $tier, $key, $benefit) {
            $locked = UserBalanceLock::acquire($user->id);
            $existing = $locked->coupons()->where('redemption_key', $key)->first();
            if ($existing) {
                if ($existing->is_demo || $existing->tier !== $tier) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Identificador já usado para outro benefício.']);
                }

                return $existing;
            }
            $cost = DecimalAmount::units($benefit['points']);
            if (DecimalAmount::units($locked->points) < $cost) {
                throw new InsufficientPointsException('Pontos insuficientes para resgatar este cupom.');
            }
            if (! User::whereKey($locked->id)->where('points', '>=', $benefit['points'])->decrement('points', $benefit['points'])) {
                throw new InsufficientPointsException('Pontos insuficientes.');
            }
            $coupon = Coupon::create(['user_id' => $locked->id, 'code' => 'PT-'.Str::upper((string) Str::uuid()), 'tier' => $tier, 'name' => $benefit['name'], 'discount_percent' => $benefit['percent'], 'points_cost' => $benefit['points'], 'status' => 'available', 'redemption_key' => $key, 'redeemed_at' => now()]);
            PointMovement::create(['user_id' => $locked->id, 'coupon_id' => $coupon->id, 'type' => 'redemption', 'amount' => DecimalAmount::decimal(-$cost), 'balance_after' => $locked->refresh()->points, 'description' => "Resgate do cupom {$benefit['name']}", 'event_key' => "coupon:{$coupon->id}"]);

            return $coupon;
        }, 5);
    }
}
