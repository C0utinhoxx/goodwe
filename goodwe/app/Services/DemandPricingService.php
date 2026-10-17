<?php

namespace App\Services;

use App\Contracts\DemandDetector;
use App\Models\DemandPeak;
use App\Models\Establishment;
use App\Models\TariffEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemandPricingService
{
    public function detectorConfigured(): bool
    {
        $detector = config('demand.detector');

        return is_string($detector) && is_subclass_of($detector, DemandDetector::class);
    }

    private function lock(int $id): Establishment
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Establishment::whereKey($id)->update(['updated_at' => DB::raw('updated_at')]);
        }

        return Establishment::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function validateSignal(Establishment $establishment, string $key, string $criteria, string $type, array $evidence, CarbonInterface $at): void
    {
        if ($key === '' || strlen($key) > 128 || $criteria === '' || strlen($criteria) > 128 || $at->isFuture() || ! $this->detectorConfigured() || ! app(config('demand.detector'))->accepts($establishment, $criteria, $type, $evidence)) {
            throw ValidationException::withMessages(['demand' => 'Evento de demanda sem detector configurado, critérios válidos ou confirmação temporal válida.']);
        }
    }

    /** Receives confirmed signals exclusively from a future trusted detector; there is no HTTP endpoint for starting peaks. */
    public function beginConfirmedPeak(Establishment $establishment, string $externalId, string $criteriaReference, array $evidence, CarbonInterface $at): DemandPeak
    {
        return DB::transaction(function () use ($establishment, $externalId, $criteriaReference, $evidence, $at) {
            $locked = $this->lock($establishment->id);
            $this->validateSignal($locked, $externalId, $criteriaReference, 'start', $evidence, $at);
            $existing = $locked->demandPeaks()->where('external_id', $externalId)->first();
            if ($existing) {
                if ($existing->criteria_reference !== $criteriaReference || $existing->evidence != $evidence || $existing->started_at->timestamp !== $at->timestamp) {
                    throw ValidationException::withMessages(['demand' => 'Evento repetido com conteúdo divergente.']);
                }

                return $existing;
            }
            if ($locked->active_peak_id || ! in_array($locked->pricing_mode, ['manual', 'automatic'], true) || $locked->surge_percentage === null) {
                throw ValidationException::withMessages(['demand' => 'Há um pico ativo ou a configuração de tarifação está incompleta.']);
            }
            $lastEnd = DemandPeak::where('establishment_id', $locked->id)->max('ended_at');
            if ($lastEnd && $at->lt(Carbon::parse($lastEnd))) {
                throw ValidationException::withMessages(['demand' => 'Evento anterior ao último pico encerrado.']);
            }
            $suggestion = TariffCalculator::adjusted($locked->cost_per_kwh, $locked->surge_percentage);
            $previous = $locked->currentTariff();
            $automatic = $locked->pricing_mode === 'automatic';
            $peak = DemandPeak::create(['establishment_id' => $locked->id, 'external_id' => $externalId, 'criteria_reference' => $criteriaReference, 'evidence' => $evidence, 'status' => 'active', 'decision' => $automatic ? 'applied' : 'pending', 'pricing_mode' => $locked->pricing_mode, 'base_tariff' => $locked->cost_per_kwh, 'suggested_tariff' => $suggestion, 'percentage' => $locked->surge_percentage, 'version' => 1, 'started_at' => $at]);
            $locked->update(['active_peak_id' => $peak->id, 'peak_tariff' => $automatic ? $suggestion : null]);
            $this->record($locked, $peak, 'peak_identified', $previous, $previous);
            if ($automatic && $previous !== $locked->currentTariff()) {
                $this->record($locked, $peak, 'automatic_adjustment', $previous, $locked->currentTariff());
            }

            return $peak;
        }, 5);
    }

    public function decide(User $manager, DemandPeak $peak, bool $approve, int $version): DemandPeak
    {
        return DB::transaction(function () use ($manager, $peak, $approve, $version) {
            $locked = $this->lock($peak->establishment_id);
            abort_unless($manager->isGestor() && $locked->user_id === $manager->id, 403);
            $event = DemandPeak::whereKey($peak->id)->lockForUpdate()->firstOrFail();
            if ($event->status !== 'active' || $locked->active_peak_id !== $event->id || $locked->pricing_mode !== 'manual' || $event->version !== $version) {
                throw ValidationException::withMessages(['demand' => 'Esta proposta expirou ou foi atualizada. Confira a tarifa vigente antes de decidir.']);
            }
            $desired = $approve ? 'applied' : 'rejected';
            if ($event->decision === $desired) {
                return $event;
            }
            if ($event->decision !== 'pending') {
                throw ValidationException::withMessages(['demand' => 'Já existe uma decisão para este pico.']);
            }
            $previous = $locked->currentTariff();
            $event->update(['decision' => $desired]);
            $locked->update(['peak_tariff' => $approve ? $event->suggested_tariff : null]);
            $this->record($locked, $event, $approve ? 'approved' : 'rejected', $previous, $locked->currentTariff(), $manager);

            return $event;
        }, 5);
    }

    /** A confirmed end must identify its original peak; late events cannot end a later peak. */
    public function endConfirmedPeak(Establishment $establishment, string $peakReference, string $eventId, string $criteriaReference, array $evidence, CarbonInterface $at): DemandPeak
    {
        return DB::transaction(function () use ($establishment, $peakReference, $eventId, $criteriaReference, $evidence, $at) {
            $locked = $this->lock($establishment->id);
            $this->validateSignal($locked, $eventId, $criteriaReference, 'end', $evidence, $at);
            $peak = DemandPeak::where('establishment_id', $locked->id)->where('external_id', $peakReference)->lockForUpdate()->firstOrFail();
            if ($criteriaReference !== $peak->criteria_reference || $at->lt($peak->started_at)) {
                throw ValidationException::withMessages(['demand' => 'Encerramento incompatível com o pico identificado.']);
            }
            if ($peak->status === 'ended') {
                if ($peak->end_event_id !== $eventId || $peak->ended_at->timestamp !== $at->timestamp || $peak->end_evidence != $evidence) {
                    throw ValidationException::withMessages(['demand' => 'Confirmação de encerramento divergente.']);
                }

                return $peak;
            }
            if ($locked->active_peak_id !== $peak->id || DemandPeak::where('establishment_id', $locked->id)->where('end_event_id', $eventId)->exists()) {
                throw ValidationException::withMessages(['demand' => 'Este encerramento não corresponde ao pico ativo.']);
            }
            $previous = $locked->currentTariff();
            $peak->update(['status' => 'ended', 'ended_at' => $at, 'end_event_id' => $eventId, 'end_evidence' => $evidence]);
            $locked->update(['active_peak_id' => null, 'peak_tariff' => null]);
            $this->record($locked, $peak, 'peak_ended', $previous, $previous);
            if ($previous !== $locked->currentTariff()) {
                $this->record($locked, $peak, 'base_restored', $previous, $locked->currentTariff());
            }

            return $peak;
        }, 5);
    }

    public function updateSettings(User $manager, Establishment $establishment, array $data): Establishment
    {
        try {
            return DB::transaction(function () use ($manager, $establishment, $data) {
                $locked = $this->lock($establishment->id);
                abort_unless($manager->isGestor() && $locked->user_id === $manager->id, 403);
                $previous = $locked->currentTariff();
                $locked->fill($data);
                $changes = $locked->getDirty();
                $pricingChanged = $locked->isDirty(['cost_per_kwh', 'pricing_mode', 'surge_percentage']);
                if ($pricingChanged && $locked->active_peak_id) {
                    $peak = DemandPeak::whereKey($locked->active_peak_id)->lockForUpdate()->firstOrFail();
                    $suggestion = TariffCalculator::adjusted($locked->cost_per_kwh, $locked->surge_percentage);
                    $applied = $peak->decision === 'applied' || $locked->pricing_mode === 'automatic';
                    $peak->update(['pricing_mode' => $locked->pricing_mode, 'base_tariff' => $locked->cost_per_kwh, 'percentage' => $locked->surge_percentage, 'suggested_tariff' => $suggestion, 'version' => $peak->version + 1, 'decision' => $applied ? 'applied' : $peak->decision]);
                    $locked->peak_tariff = $applied ? $suggestion : null;
                }
                $locked->save();
                if ($changes !== []) {
                    $this->record($locked, null, 'configuration_updated', $previous, $locked->currentTariff(), $manager, ['changed_fields' => array_keys($changes)]);
                }

                return $locked;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['cnpj' => 'Este CNPJ já está vinculado a outro estabelecimento.']);
        }
    }

    private function record(Establishment $establishment, ?DemandPeak $peak, string $type, ?string $previous, string $next, ?User $actor = null, array $metadata = []): void
    {
        TariffEvent::create(['establishment_id' => $establishment->id, 'demand_peak_id' => $peak?->id, 'actor_id' => $actor?->id, 'type' => $type, 'previous_tariff' => $previous, 'new_tariff' => $next, 'percentage' => $peak?->percentage ?? $establishment->surge_percentage, 'pricing_mode' => $peak?->pricing_mode ?? $establishment->pricing_mode, 'metadata' => $metadata, 'occurred_at' => now()]);
    }
}
