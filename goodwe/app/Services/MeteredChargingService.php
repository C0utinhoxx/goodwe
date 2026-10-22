<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Charger;
use App\Models\ChargingEvent;
use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Receives normalized, authenticated confirmations from the future IoT adapter, never from web forms. */
class MeteredChargingService
{
    public function __construct(private ReservationService $reservations, private DemandControlService $demand, private ReservationTagService $tags) {}

    /** Checks eligibility before the future adapter authorizes energy; this does not start a session or hold cash. */
    public function assertCanAuthorize(User $user, Charger $charger, ?int $reservationId = null): void
    {
        abort_if($charger->is_demo, 404);
        $this->reservations->expireForCharger($charger);
        DB::transaction(function () use ($user, $charger, $reservationId) {
            $locked = ChargerLock::acquire($charger->id);
            $establishment = Establishment::whereKey($locked->establishment_id)->lockForUpdate()->firstOrFail();
            $payer = UserBalanceLock::acquire($user->id);
            if (DecimalAmount::units($payer->balance) <= 0) {
                throw new InsufficientBalanceException('Adicione créditos à carteira antes de iniciar a recarga.');
            }
            if (($locked->activation_status !== null && $locked->activation_status !== 'active') || $locked->physical_status !== 'available' || $locked->active_session_id) {
                throw ValidationException::withMessages(['charger' => 'Este equipamento não pode receber uma nova autorização.']);
            }
            $reservation = $reservationId ? ChargingReservation::whereKey($reservationId)->lockForUpdate()->first() : null;
            if ($reservationId && (! $reservation || $reservation->user_id !== $user->id || $reservation->charger_id !== $charger->id || $reservation->status !== 'active' || $reservation->expires_at->lte(now()) || $locked->active_reservation_id !== $reservation->id)) {
                throw ValidationException::withMessages(['reservation' => 'Reserva inválida para autorização.']);
            }
            if (! $reservation && $locked->active_reservation_id) {
                throw ValidationException::withMessages(['reservation' => 'Este equipamento já está reservado.']);
            }
            EnergyBilling::amount('0', $reservation?->tariff_per_kwh ?? $locked->currentTariff());
            $this->demand->assertCanStartSession($establishment, $locked);
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $attributes  Atributos internos aplicados na criação (ex.: simulação web).
     */
    public function startConfirmed(User $user, Charger $charger, string $externalSessionId, ?int $reservationId = null, array $attributes = []): ChargingSession
    {
        abort_if($charger->is_demo, 404);
        $this->reservations->expireForCharger($charger);

        $session = DB::transaction(function () use ($user, $charger, $externalSessionId, $reservationId, $attributes) {
            $lockedCharger = ChargerLock::acquire($charger->id);
            $establishment = Establishment::whereKey($lockedCharger->establishment_id)->lockForUpdate()->firstOrFail();
            $lockedUser = UserBalanceLock::acquire($user->id);
            $existing = $lockedCharger->sessions()->where('external_session_id', $externalSessionId)->first();
            if ($existing) {
                if ($existing->user_id !== $user->id || $existing->charging_reservation_id !== $reservationId) {
                    throw ValidationException::withMessages(['session' => 'Confirmação associada a outra sessão.']);
                }

                return $existing;
            }
            if ($externalSessionId === '' || strlen($externalSessionId) > 128) {
                throw ValidationException::withMessages(['session' => 'Identificador da confirmação inválido.']);
            }
            if (DecimalAmount::units($lockedUser->balance) <= 0) {
                throw new InsufficientBalanceException('Adicione créditos à carteira antes de iniciar a recarga.');
            }
            if (($lockedCharger->activation_status !== null && $lockedCharger->activation_status !== 'active') || $lockedCharger->active_session_id || ! in_array($lockedCharger->physical_status, ['available', 'in_use'], true)) {
                throw ValidationException::withMessages(['charger' => 'Carregador não autorizado para uma nova sessão.']);
            }
            $reservation = $reservationId ? ChargingReservation::whereKey($reservationId)->lockForUpdate()->first() : null;
            if ($reservationId && (! $reservation || $reservation->user_id !== $user->id || $reservation->charger_id !== $charger->id || $reservation->status !== 'active' || $reservation->expires_at->lte(now()) || $lockedCharger->active_reservation_id !== $reservation->id)) {
                throw ValidationException::withMessages(['reservation' => 'Reserva inválida ou expirada; sua tarifa garantida não pode ser utilizada.']);
            }
            if (! $reservation && $lockedCharger->active_reservation_id) {
                throw ValidationException::withMessages(['reservation' => 'Carregador reservado por outra operação.']);
            }
            $tariff = $reservation?->tariff_per_kwh ?? $lockedCharger->currentTariff();
            EnergyBilling::amount('0', $tariff);
            $this->demand->assertCanStartSession($establishment, $lockedCharger);
            $session = ChargingSession::create(array_merge([
                'user_id' => $user->id,
                'charger_id' => $charger->id,
                'establishment_id' => $establishment->id,
                'charging_reservation_id' => $reservation?->id,
                'external_session_id' => $externalSessionId,
                'charger_name' => $lockedCharger->name,
                'tariff_per_kwh' => $tariff,
                'energy_kwh' => 0,
                'measured_energy_kwh' => '0.0000',
                'amount_charged' => 0,
                'original_amount' => 0,
                'discount_amount' => 0,
                'status' => 'pending',
                'charging_state' => 'charging',
                'payment_status' => 'not_due',
                'payment_method' => 'wallet',
                'started_at' => now(),
            ], $attributes));
            $reservation?->update(['status' => 'started', 'started_at' => now()]);
            $lockedCharger->update(['active_reservation_id' => null, 'active_session_id' => $session->id, 'physical_status' => 'in_use', 'physical_status_updated_at' => now()]);
            $this->event($session, 'start_confirmed', 'start', ['external_session_id' => $externalSessionId, 'tariff_per_kwh' => $session->tariff_per_kwh]);

            return $session;
        }, 5);

        // A reserva consumida libera o flag `tag_reservada` no Firestore:
        // o ponto passou de garantido para sessão em andamento.
        if ($reservationId !== null && ($startedReservation = ChargingReservation::find($reservationId)) !== null) {
            $this->tags->sync($startedReservation);
        }

        return $session;
    }

    public function recordConsumption(ChargingSession $session, string $cumulativeEnergy, int $sequence, string $eventId): ChargingSession
    {
        abort_if($session->is_demo, 404);

        return DB::transaction(function () use ($session, $cumulativeEnergy, $sequence, $eventId) {
            ChargerLock::acquire($session->charger_id);
            $user = UserBalanceLock::acquire($session->user_id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            return $this->recordConsumptionLocked($locked, $user, $cumulativeEnergy, $sequence, $eventId);
        }, 5);
    }

    /**
     * Registra consumo quando o chamador já detém transação, locks e a sessão
     * atualizada. Evita repetir locks/savepoints no tick da simulação web.
     */
    public function recordConsumptionLocked(ChargingSession $session, User $user, string $cumulativeEnergy, int $sequence, string $eventId): ChargingSession
    {
        $units = EnergyBilling::units($cumulativeEnergy);
        $key = "session:{$session->id}:meter:{$eventId}";
        $existing = ChargingEvent::where('event_key', $key)->first();
        if ($existing) {
            if (($existing->metadata['energy_units'] ?? null) !== $units || ($existing->metadata['sequence'] ?? null) !== $sequence) {
                throw ValidationException::withMessages(['event' => 'Evento repetido com dados diferentes.']);
            }

            return $session;
        }
        if (! in_array($session->charging_state, ['charging', 'stop_requested'], true) || $sequence <= 0 || $eventId === '' || strlen($eventId) > 128) {
            throw ValidationException::withMessages(['session' => 'A sessão não aceita esta medição.']);
        }
        $stale = $sequence <= $session->last_meter_sequence;
        if (! $stale && $units < EnergyBilling::units($session->measured_energy_kwh)) {
            throw ValidationException::withMessages(['energy' => 'O consumo acumulado não pode diminuir.']);
        }
        $this->event($session, 'meter', $eventId, ['energy_units' => $units, 'sequence' => $sequence, 'stale' => $stale]);
        if ($stale) {
            return $session;
        }
        $amount = EnergyBilling::amount($cumulativeEnergy, $session->tariff_per_kwh);
        $session->update(['measured_energy_kwh' => $cumulativeEnergy, 'energy_kwh' => round((float) $cumulativeEnergy, 2), 'original_amount' => $amount, 'last_meter_sequence' => $sequence, 'last_meter_at' => now(), 'balance_exceeded' => DecimalAmount::units($amount) > DecimalAmount::units($user->balance)]);
        if (DecimalAmount::units($amount) >= DecimalAmount::units($user->balance) && ! $session->stop_requested_at) {
            $session->update(['charging_state' => 'stop_requested', 'stop_requested_at' => now()]);
            $this->event($session, 'stop_required', 'balance', ['measured_amount' => $amount, 'wallet_balance' => $user->balance, 'command_dispatched' => false, 'reason' => 'wallet_balance']);
        }

        return $session;
    }

    public function endConfirmed(ChargingSession $session, string $finalEnergy, string $eventId, bool $stopConfirmed = false): ChargingSession
    {
        abort_if($session->is_demo, 404);

        return DB::transaction(function () use ($session, $finalEnergy, $eventId, $stopConfirmed) {
            $charger = ChargerLock::acquire($session->charger_id);
            $user = UserBalanceLock::acquire($session->user_id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            return $this->endConfirmedLocked($locked, $charger, $user, $finalEnergy, $eventId, $stopConfirmed);
        }, 5);
    }

    /**
     * Encerra a sessão quando o chamador já detém transação, locks e a sessão
     * atualizada.
     */
    public function endConfirmedLocked(ChargingSession $session, Charger $charger, User $user, string $finalEnergy, string $eventId, bool $stopConfirmed = false): ChargingSession
    {
        $units = EnergyBilling::units($finalEnergy);
        if ($session->charging_state === 'ended') {
            if ($units !== EnergyBilling::units($session->measured_energy_kwh)) {
                throw ValidationException::withMessages(['energy' => 'Consumo final já confirmado; divergência exige conciliação.']);
            }

            return $session;
        }
        if (! in_array($session->charging_state, ['charging', 'stop_requested'], true) || $units < EnergyBilling::units($session->measured_energy_kwh) || $eventId === '' || strlen($eventId) > 128) {
            throw ValidationException::withMessages(['session' => 'Encerramento inválido.']);
        }
        $amount = EnergyBilling::amount($finalEnergy, $session->tariff_per_kwh);
        $session->update(['measured_energy_kwh' => $finalEnergy, 'energy_kwh' => round((float) $finalEnergy, 2), 'original_amount' => $amount, 'charging_state' => 'ended', 'payment_status' => 'pending', 'ended_at' => now(), 'stop_confirmed_at' => $stopConfirmed ? now() : null, 'balance_exceeded' => DecimalAmount::units($amount) > DecimalAmount::units($user->balance)]);
        if ($charger->active_session_id === $session->id) {
            $charger->update(['active_session_id' => null]);
        }
        $this->event($session, 'end_confirmed', $eventId, ['energy_units' => $units, 'stop_confirmed' => $stopConfirmed]);

        return $session;
    }

    public function physicalStatusConfirmed(Charger $charger, string $status, string $eventId): void
    {
        if (! in_array($status, ['available', 'in_use', 'unavailable'], true) || $eventId === '' || strlen($eventId) > 128) {
            throw ValidationException::withMessages(['status' => 'Estado físico inválido.']);
        }
        DB::transaction(function () use ($charger, $status, $eventId) {
            $locked = ChargerLock::acquire($charger->id);
            $key = "charger:{$locked->id}:physical:{$eventId}";
            $existing = ChargingEvent::where('event_key', $key)->first();
            if ($existing) {
                if ($existing->metadata['status'] !== $status) {
                    throw ValidationException::withMessages(['event' => 'Evento físico divergente.']);
                }

                return;
            }
            $locked->update(['physical_status' => $status, 'physical_status_updated_at' => now()]);
            ChargingEvent::create(['charger_id' => $locked->id, 'type' => 'physical_status', 'event_key' => $key, 'metadata' => ['status' => $status], 'occurred_at' => now()]);
        }, 5);
    }

    private function event(ChargingSession $session, string $type, string $eventId, array $metadata): void
    {
        ChargingEvent::create(['charger_id' => $session->charger_id, 'charging_session_id' => $session->id, 'charging_reservation_id' => $session->charging_reservation_id, 'type' => $type, 'event_key' => "session:{$session->id}:{$type}:{$eventId}", 'metadata' => $metadata + ($session->isWebSimulation() ? ['simulated' => true, 'physical_confirmation' => false, 'command_dispatched' => false, 'source' => 'web_test'] : []), 'occurred_at' => now()]);
    }
}
