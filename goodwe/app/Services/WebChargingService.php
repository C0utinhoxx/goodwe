<?php

namespace App\Services;

use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebChargingService
{
    public const SPEEDS = [1, 60, 300];

    public static function available(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function __construct(private MeteredChargingService $metered, private ReservationService $reservations) {}

    public function start(User $user, ChargingReservation $reservation): ChargingSession
    {
        abort_unless(self::available(), 403);
        $charger = $reservation->charger;
        abort_unless($reservation->user_id === $user->id && ! $reservation->is_demo && ! $charger->is_demo, 403);

        // A expiração só custa locks e transação quando a reserva já venceu;
        // antes disso era trabalho pago em todos os inícios.
        if ($reservation->expires_at->isPast()) {
            $this->reservations->expire($reservation);
        }

        $existing = $user->chargingSessions()->where('charging_reservation_id', $reservation->id)->first();
        if ($existing) {
            if (! $existing->isWebSimulation()) {
                throw ValidationException::withMessages(['reservation' => 'Esta reserva já possui uma sessão confirmada pelo equipamento.']);
            }

            return $existing;
        }

        if (($charger->activation_status !== null && $charger->activation_status !== 'active') || $charger->physical_status !== 'available' || $charger->active_session_id) {
            throw ValidationException::withMessages(['charger' => 'Este equipamento não pode receber uma nova autorização.']);
        }

        // `startConfirmed` repete as validações de saldo, carregador,
        // reserva e demanda sob lock; chamar antes `assertCanAuthorize`
        // duplicava a ronda de locks no caminho crítico do início.
        return $this->metered->startConfirmed(
            $user,
            $charger,
            'web-test-reservation:'.$reservation->id,
            $reservation->id,
            [
                'payment_method' => ChargingSession::PAYMENT_SIMULATED,
                'simulation' => ['clock_at' => now()->toIso8601String(), 'power_kw' => $charger->power_kw, 'elapsed_seconds' => 0, 'speed' => 1],
            ]
        );
    }

    public function advance(User $user, ChargingSession $session, ?int $speed = null, bool $end = false): ChargingSession
    {
        abort_unless(self::available(), 403);
        abort_unless($session->user_id === $user->id && $session->isWebSimulation() && ! $session->is_demo, 403);
        if ($speed !== null && ! in_array($speed, self::SPEEDS, true)) {
            throw ValidationException::withMessages(['speed' => 'Velocidade de simulação inválida.']);
        }

        return DB::transaction(function () use ($user, $session, $speed, $end) {
            $charger = ChargerLock::acquire($session->charger_id);
            $payer = UserBalanceLock::acquire($user->id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->charging_state === 'ended') {
                return $locked;
            }
            if ($locked->charging_state !== 'charging') {
                throw ValidationException::withMessages(['session' => 'Esta sessão não está em simulação ativa.']);
            }
            $state = $locked->simulation;
            $realSeconds = max(0, now()->getTimestamp() - Carbon::parse($state['clock_at'])->getTimestamp());
            $elapsed = min(86400, $state['elapsed_seconds'] + $realSeconds * $state['speed']);
            $power = DecimalAmount::units($state['power_kw']);
            $energyUnits = intdiv($power * $elapsed * 100, 3600);
            $balance = min(9999999999, DecimalAmount::units($payer->balance));
            $limit = max(0, intdiv($balance * 1000000, EnergyBilling::units($locked->tariff_per_kwh)));
            $atLimit = $energyUnits >= $limit;
            if ($atLimit) {
                $energyUnits = max(EnergyBilling::units($locked->measured_energy_kwh), $limit);
                $elapsed = $power > 0 ? (int) ceil($energyUnits * 3600 / ($power * 100)) : 0;
            }
            $energy = sprintf('%d.%04d', intdiv($energyUnits, 10000), $energyUnits % 10000);
            $locked->update(['simulation' => [...$state, 'elapsed_seconds' => $elapsed, 'clock_at' => now()->toIso8601String(), 'speed' => $speed ?? $state['speed']]]);
            if ($energyUnits > EnergyBilling::units($locked->measured_energy_kwh)) {
                $sequence = $locked->last_meter_sequence + 1;
                $locked = $this->metered->recordConsumptionLocked($locked, $payer, $energy, $sequence, 'web-tick:'.$sequence);
            }
            if ($end || $atLimit || $locked->charging_state === 'stop_requested' || $elapsed >= 86400) {
                $locked = $this->metered->endConfirmedLocked($locked, $charger, $payer, $energy, 'web-test-end', false);
                $charger->update(['physical_status' => 'available']);
            }

            return $locked;
        }, 5);
    }
}
