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
    public function __construct(private ReservationService $reservations) {}

    /** Checks eligibility before the future adapter authorizes energy; this does not start a session or hold cash. */
    public function assertCanAuthorize(User $user, Charger $charger, ?int $reservationId = null): void
    {
        $this->reservations->expireForCharger($charger);
        DB::transaction(function () use ($user, $charger, $reservationId) {
            $locked = ChargerLock::acquire($charger->id);
            $establishment = Establishment::whereKey($locked->establishment_id)->lockForUpdate()->firstOrFail();
            $payer = UserBalanceLock::acquire($user->id);
            if (DecimalAmount::units($payer->balance) <= 0) {
                throw new InsufficientBalanceException('Adicione créditos à carteira antes de iniciar a recarga.');
            }
            if ($locked->physical_status !== 'available' || $locked->active_session_id) {
                throw ValidationException::withMessages(['charger' => 'Este equipamento não pode receber uma nova autorização.']);
            }
            $reservation = $reservationId ? ChargingReservation::whereKey($reservationId)->lockForUpdate()->first() : null;
            if ($reservationId && (! $reservation || $reservation->user_id !== $user->id || $reservation->charger_id !== $charger->id || $reservation->status !== 'active' || $reservation->expires_at->lte(now()) || $locked->active_reservation_id !== $reservation->id)) {
                throw ValidationException::withMessages(['reservation' => 'Reserva inválida para autorização.']);
            }
            if (! $reservation && $locked->active_reservation_id) {
                throw ValidationException::withMessages(['reservation' => 'Este equipamento já está reservado.']);
            }
            EnergyBilling::amount('0', $reservation?->tariff_per_kwh ?? $locked->tariff_override ?? $establishment->cost_per_kwh);
        }, 5);
    }

    public function startConfirmed(User $user, Charger $charger, string $externalSessionId, ?int $reservationId = null): ChargingSession
    {
        $this->reservations->expireForCharger($charger);

        return DB::transaction(function () use ($user, $charger, $externalSessionId, $reservationId) {
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
            if ($lockedCharger->active_session_id || ! in_array($lockedCharger->physical_status, ['available', 'in_use'], true)) {
                throw ValidationException::withMessages(['charger' => 'Carregador não autorizado para uma nova sessão.']);
            }
            $reservation = $reservationId ? ChargingReservation::whereKey($reservationId)->lockForUpdate()->first() : null;
            if ($reservationId && (! $reservation || $reservation->user_id !== $user->id || $reservation->charger_id !== $charger->id || $reservation->status !== 'active' || $reservation->expires_at->lte(now()) || $lockedCharger->active_reservation_id !== $reservation->id)) {
                throw ValidationException::withMessages(['reservation' => 'Reserva inválida ou expirada; sua tarifa garantida não pode ser utilizada.']);
            }
            if (! $reservation && $lockedCharger->active_reservation_id) {
                throw ValidationException::withMessages(['reservation' => 'Carregador reservado por outra operação.']);
            }
            $tariff = $reservation?->tariff_per_kwh ?? $lockedCharger->tariff_override ?? $establishment->cost_per_kwh;
            EnergyBilling::amount('0', $tariff);
            $session = ChargingSession::create([
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
            ]);
            $reservation?->update(['status' => 'started', 'started_at' => now()]);
            $lockedCharger->update(['active_reservation_id' => null, 'active_session_id' => $session->id, 'physical_status' => 'in_use', 'physical_status_updated_at' => now()]);
            $this->event($session, 'start_confirmed', 'start', ['external_session_id' => $externalSessionId, 'tariff_per_kwh' => $session->tariff_per_kwh]);

            return $session;
        }, 5);
    }

    public function recordConsumption(ChargingSession $session, string $cumulativeEnergy, int $sequence, string $eventId): ChargingSession
    {
        $units = EnergyBilling::units($cumulativeEnergy);

        return DB::transaction(function () use ($session, $cumulativeEnergy, $units, $sequence, $eventId) {
            ChargerLock::acquire($session->charger_id);
            $user = UserBalanceLock::acquire($session->user_id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $key = "session:{$locked->id}:meter:{$eventId}";
            $existing = ChargingEvent::where('event_key', $key)->first();
            if ($existing) {
                if (($existing->metadata['energy_units'] ?? null) !== $units || ($existing->metadata['sequence'] ?? null) !== $sequence) {
                    throw ValidationException::withMessages(['event' => 'Evento repetido com dados diferentes.']);
                }

                return $locked;
            }
            if (! in_array($locked->charging_state, ['charging', 'stop_requested'], true) || $sequence <= 0 || $eventId === '' || strlen($eventId) > 128) {
                throw ValidationException::withMessages(['session' => 'A sessão não aceita esta medição.']);
            }
            $stale = $sequence <= $locked->last_meter_sequence;
            if (! $stale && $units < EnergyBilling::units($locked->measured_energy_kwh)) {
                throw ValidationException::withMessages(['energy' => 'O consumo acumulado não pode diminuir.']);
            }
            $this->event($locked, 'meter', $eventId, ['energy_units' => $units, 'sequence' => $sequence, 'stale' => $stale]);
            if ($stale) {
                return $locked;
            }
            $amount = EnergyBilling::amount($cumulativeEnergy, $locked->tariff_per_kwh);
            $locked->update(['measured_energy_kwh' => $cumulativeEnergy, 'energy_kwh' => round((float) $cumulativeEnergy, 2), 'original_amount' => $amount, 'last_meter_sequence' => $sequence, 'last_meter_at' => now(), 'balance_exceeded' => DecimalAmount::units($amount) > DecimalAmount::units($user->balance)]);
            if (DecimalAmount::units($amount) >= DecimalAmount::units($user->balance) && ! $locked->stop_requested_at) {
                $locked->update(['charging_state' => 'stop_requested', 'stop_requested_at' => now()]);
                $this->event($locked, 'stop_required', 'balance', ['measured_amount' => $amount, 'wallet_balance' => $user->balance, 'command_dispatched' => false, 'reason' => 'wallet_balance']);
            }

            return $locked;
        }, 5);
    }

    public function endConfirmed(ChargingSession $session, string $finalEnergy, string $eventId, bool $stopConfirmed = false): ChargingSession
    {
        $units = EnergyBilling::units($finalEnergy);

        return DB::transaction(function () use ($session, $finalEnergy, $units, $eventId, $stopConfirmed) {
            $charger = ChargerLock::acquire($session->charger_id);
            $user = UserBalanceLock::acquire($session->user_id);
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->charging_state === 'ended') {
                if ($units !== EnergyBilling::units($locked->measured_energy_kwh)) {
                    throw ValidationException::withMessages(['energy' => 'Consumo final já confirmado; divergência exige conciliação.']);
                }

                return $locked;
            }
            if (! in_array($locked->charging_state, ['charging', 'stop_requested'], true) || $units < EnergyBilling::units($locked->measured_energy_kwh) || $eventId === '' || strlen($eventId) > 128) {
                throw ValidationException::withMessages(['session' => 'Encerramento inválido.']);
            }
            $amount = EnergyBilling::amount($finalEnergy, $locked->tariff_per_kwh);
            $locked->update(['measured_energy_kwh' => $finalEnergy, 'energy_kwh' => round((float) $finalEnergy, 2), 'original_amount' => $amount, 'charging_state' => 'ended', 'payment_status' => 'pending', 'ended_at' => now(), 'stop_confirmed_at' => $stopConfirmed ? now() : null, 'balance_exceeded' => DecimalAmount::units($amount) > DecimalAmount::units($user->balance)]);
            if ($charger->active_session_id === $locked->id) {
                $charger->update(['active_session_id' => null]);
            }
            $this->event($locked, 'end_confirmed', $eventId, ['energy_units' => $units, 'stop_confirmed' => $stopConfirmed]);

            return $locked;
        }, 5);
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
        ChargingEvent::create(['charger_id' => $session->charger_id, 'charging_session_id' => $session->id, 'charging_reservation_id' => $session->charging_reservation_id, 'type' => $type, 'event_key' => "session:{$session->id}:{$type}:{$eventId}", 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
