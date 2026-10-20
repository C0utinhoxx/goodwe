<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\ChargingReservation;
use App\Models\Establishment;
use App\Models\PointMovement;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    /** Guard que evita varrer reservas vencidas a cada requisição do portal. */
    private const EXPIRE_DUE_GUARD_KEY = 'reservations:expire-due-guard';

    public function reserve(User $user, Charger $charger, string $key): ChargingReservation
    {
        abort_if($charger->is_demo, 404);
        $this->expireForCharger($charger);

        return DB::transaction(function () use ($user, $charger, $key) {
            $lockedCharger = ChargerLock::acquire($charger->id);
            $establishment = Establishment::whereKey($lockedCharger->establishment_id)->lockForUpdate()->firstOrFail();
            $lockedCharger->setRelation('establishment', $establishment);
            UserBalanceLock::acquire($user->id);
            $existing = $user->reservations()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->charger_id !== $lockedCharger->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Esta solicitação já foi usada para outro carregador.']);
                }

                return $existing;
            }
            if (! $lockedCharger->isAvailable()) {
                throw ValidationException::withMessages(['charger' => 'Este carregador não está disponível para reserva.']);
            }
            if (! $establishment->reservation_tolerance_minutes || (float) $lockedCharger->currentTariff() <= 0) {
                throw ValidationException::withMessages(['charger' => 'O estabelecimento precisa configurar a tolerância e uma tarifa válida antes de aceitar reservas.']);
            }
            if ($establishment->reservation_tolerance_minutes > intdiv(PHP_INT_MAX, 60) || ($establishment->no_show_penalty_points !== null && (float) $establishment->no_show_penalty_points < 0)) {
                throw ValidationException::withMessages(['charger' => 'Configuração de reserva inválida.']);
            }
            $seconds = $establishment->reservation_tolerance_minutes * 60;
            $reservation = ChargingReservation::create([
                'user_id' => $user->id,
                'charger_id' => $lockedCharger->id,
                'establishment_id' => $establishment->id,
                'idempotency_key' => $key,
                'status' => 'active',
                'tariff_per_kwh' => $lockedCharger->currentTariff(),
                'tolerance_seconds' => $seconds,
                'no_show_penalty_points' => $establishment->no_show_penalty_points,
                'reserved_at' => now(),
                'expires_at' => now()->addSeconds($seconds),
            ]);
            $lockedCharger->update(['active_reservation_id' => $reservation->id]);
            $this->event($reservation, 'reserved', ['tariff_per_kwh' => $reservation->tariff_per_kwh, 'tolerance_seconds' => $seconds]);

            return $reservation;
        }, 5);
    }

    public function cancel(User $user, ChargingReservation $reservation): ChargingReservation
    {
        abort_unless($reservation->user_id === $user->id, 403);
        $this->expire($reservation);

        return DB::transaction(function () use ($user, $reservation) {
            $charger = ChargerLock::acquire($reservation->charger_id);
            UserBalanceLock::acquire($user->id);
            $locked = ChargingReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            if ($locked->status !== 'active' || $locked->started_at || $locked->expires_at->lte(now())) {
                throw ValidationException::withMessages(['reservation' => 'Esta reserva não pode mais ser cancelada.']);
            }
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            if ($charger->active_reservation_id === $locked->id) {
                $charger->update(['active_reservation_id' => null]);
            }
            $this->event($locked, 'cancelled', ['penalty_applied' => false]);

            return $locked;
        }, 5);
    }

    public function expire(ChargingReservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {
            $charger = ChargerLock::acquire($reservation->charger_id);
            $user = UserBalanceLock::acquire($reservation->user_id);
            $locked = ChargingReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active' || $locked->started_at || $locked->expires_at->isFuture()) {
                return false;
            }
            $deducted = $locked->no_show_penalty_points === null ? 0 : min(DecimalAmount::units($user->points), DecimalAmount::units($locked->no_show_penalty_points));
            $deducted = max(0, $deducted);
            if ($locked->no_show_penalty_points !== null) {
                $remaining = DecimalAmount::decimal(DecimalAmount::units($user->points) - $deducted);
                $user->update(['points' => $remaining]);
                PointMovement::create([
                    'user_id' => $user->id,
                    'type' => 'penalty',
                    'amount' => DecimalAmount::decimal(-$deducted),
                    'balance_after' => $remaining,
                    'description' => 'Ausência na reserva #'.$locked->id,
                    'event_key' => 'reservation-no-show:'.$locked->id,
                    'metadata' => ['reservation_id' => $locked->id, 'configured_points' => $locked->no_show_penalty_points, 'applied_points' => DecimalAmount::decimal($deducted)],
                ]);
            }
            $locked->update(['status' => 'expired', 'expired_at' => now(), 'penalty_processed_at' => now(), 'penalty_applied_points' => DecimalAmount::decimal($deducted)]);
            if ($charger->active_reservation_id === $locked->id) {
                $charger->update(['active_reservation_id' => null]);
            }
            $this->event($locked, 'no_show', ['configured_points' => $locked->no_show_penalty_points, 'applied_points' => DecimalAmount::decimal($deducted)]);

            return true;
        }, 5);
    }

    public function expireForCharger(Charger $charger): void
    {
        foreach ($charger->reservations()->where('is_demo', false)->where('status', 'active')->where('expires_at', '<=', now())->get() as $reservation) {
            $this->expire($reservation);
        }
    }

    public function expireDue(): int
    {
        $count = 0;
        foreach (ChargingReservation::where('is_demo', false)->where('status', 'active')->where('expires_at', '<=', now())->lazyById(100) as $reservation) {
            $count += $this->expire($reservation) ? 1 : 0;
        }

        return $count;
    }

    /**
     * Expiração disparada pelo caminho de requisição do portal (home e lista
     * de carregadores).
     *
     * Varredura completa a cada visita fica cara conforme a base cresce e o
     * agendador (`charging:expire-reservations`) nem sempre roda em produção;
     * por isso a varredura acontece no máximo uma vez por período, mantendo a
     * expiração "por página" sem repetir o custo em cada carregamento.
     */
    public function expireDueOnRequests(?int $throttleSeconds = null): int
    {
        $throttleSeconds ??= (int) config('reservations.expire_guard_seconds', 30);

        if ($throttleSeconds <= 0) {
            return $this->expireDue();
        }

        if (! Cache::add(self::EXPIRE_DUE_GUARD_KEY, 1, $throttleSeconds)) {
            return 0;
        }

        return $this->expireDue();
    }

    private function event(ChargingReservation $reservation, string $type, array $metadata): void
    {
        ChargingEvent::create(['charger_id' => $reservation->charger_id, 'charging_reservation_id' => $reservation->id, 'type' => $type, 'event_key' => "reservation:{$reservation->id}:{$type}", 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
