<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChargingPaymentService
{
    public function __construct(private WalletService $wallet) {}

    public function schedule(User $user, string $charger, array $data): ChargingSession
    {
        $minutes = (int) $data['duration_minutes'];

        return $this->pay($user, $charger, $data, DecimalAmount::decimal($minutes * config('loyalty.simulation.cents_per_minute')), DecimalAmount::decimal($minutes * config('loyalty.simulation.energy_hundredths_per_minute')), $minutes, $data['vehicle_plate']);
    }

    public function start(User $user, string $charger, array $data): ChargingSession
    {
        $point = ChargingSimulator::find($charger);
        if (! $point) {
            throw ValidationException::withMessages(['charger' => __('chargers.not_available')]);
        }
        $energy = DecimalAmount::units($data['energy_kwh']);
        $amount = intdiv($energy * config('loyalty.simulation.cents_per_kwh') + 50, 100);

        return $this->pay($user, $charger, $data, DecimalAmount::decimal($amount), DecimalAmount::decimal($energy), max(5, (int) ceil(($energy / 100 / max(1, $point->powerKw)) * 60)), 'APP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT));
    }

    private function pay(User $user, string $charger, array $data, string $amount, string $energy, int $minutes, string $plate): ChargingSession
    {
        if (Charger::where('code', $charger)->exists()) {
            throw ValidationException::withMessages(['charger' => 'Equipamentos cadastrados exigem confirmação física; o simulador não pode iniciar essa sessão.']);
        }
        $occupied = false;
        try {
            return DB::transaction(function () use ($user, $charger, $data, $amount, $energy, $minutes, $plate, &$occupied) {
                UserBalanceLock::acquire($user->id);
                $existing = $user->chargingSessions()->where('idempotency_key', $data['idempotency_key'])->first();
                if (! $existing) {
                    if (! ChargingSimulator::occupy($charger, $plate, $minutes)) {
                        throw ValidationException::withMessages(['charger' => __('chargers.not_available')]);
                    }
                    $occupied = true;
                }

                return $this->wallet->payCharging($user, (float) $amount, $charger, (float) $energy, isset($data['coupon_id']) ? (int) $data['coupon_id'] : null, $data['idempotency_key']);
            });
        } catch (Throwable $exception) {
            if ($occupied) {
                ChargingSimulator::release($charger);
            }
            throw $exception;
        }
    }
}
