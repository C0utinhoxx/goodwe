<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\DemandAlert;
use App\Models\DemandAlertState;
use App\Models\Establishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DemandAlertService
{
    private const START_PERCENT = 80;
    private const END_PERCENT = 70;
    private const REQUIRED_CHECKS = 3;
    private const MIN_CHECK_INTERVAL_SECONDS = 60;

    public function __construct(
        private DemandControlService $demandControl
    ) {
    }

    public function check(Establishment $establishment): array
    {
        $result = [
            'created' => 0,
            'resolved' => 0,
            'unchanged' => 0,
            'insufficient_data' => 0,
        ];

        $chargers = Charger::query()
            ->where('establishment_id', $establishment->id)
            ->where('activation_status', 'active')
            ->whereIn('physical_status', ['available', 'in_use'])
            ->get();

        $totalChargers = $chargers->count();

        $chargersInUse = $chargers
            ->filter(
                fn (Charger $charger) =>
                    $charger->status() === 'in_use'
            )
            ->count();

        if ($totalChargers > 0) {
            $occupancy = round(
                ($chargersInUse / $totalChargers) * 100,
                2
            );

            $this->evaluate(
                $establishment,
                DemandAlert::TYPE_CHARGING_PEAK,
                $occupancy,
                '%',
                [
                    'total_chargers' => $totalChargers,
                    'chargers_in_use' => $chargersInUse,
                ],
                $result
            );
        } else {
            $result['insufficient_data']++;
        }

        $demand = $this->demandControl->status($establishment);

        $maxPower = $demand['max_power_kw'];

        if ($maxPower === null || (float) $maxPower <= 0) {
            $result['insufficient_data']++;

            return $result;
        }

        $activeChargerIds = collect($demand['active_sessions'])
            ->pluck('charger_id')
            ->filter()
            ->unique()
            ->values();

        $readings = collect($demand['readings']);

        $measuredChargerIds = $readings
            ->pluck('charger_id')
            ->filter()
            ->unique()
            ->values();

        $allMeasured = $activeChargerIds->every(
            fn ($id) => $measuredChargerIds->contains($id)
        );

        $chargersInUseIds = $chargers
            ->filter(
                fn (Charger $charger) =>
                    $charger->status() === 'in_use'
            )
            ->pluck('id')
            ->values();

        $sessionsMatchOccupation = $chargersInUseIds->every(
            fn ($id) => $activeChargerIds->contains($id)
        );

        if (! $allMeasured || ! $sessionsMatchOccupation) {
            $result['insufficient_data']++;

            return $result;
        }

        $power = (float) $demand['total_active_power_kw'];

        $percentage = round(
            ($power / (float) $maxPower) * 100,
            2
        );

        $this->evaluate(
            $establishment,
            DemandAlert::TYPE_ENERGY_PEAK,
            $percentage,
            '%',
            [
                'measured_power_kw' => $power,
                'maximum_power_kw' => (float) $maxPower,
                'active_sessions' => $activeChargerIds->count(),
                'measured_chargers' => $measuredChargerIds->count(),
            ],
            $result
        );

        return $result;
    }

    private function evaluate(
        Establishment $establishment,
        string $type,
        float $value,
        string $unit,
        array $details,
        array &$result
    ): void {
        DB::transaction(function () use (
            $establishment,
            $type,
            $value,
            $unit,
            $details,
            &$result
        ) {
            Establishment::query()
                ->whereKey($establishment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $state = DemandAlertState::query()
                ->where('establishment_id', $establishment->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (! $state) {
                $state = DemandAlertState::create([
                    'establishment_id' => $establishment->id,
                    'type' => $type,
                    'condition' => DemandAlertState::CONDITION_NORMAL,
                    'consecutive_checks' => 0,
                ]);
            }

            $now = now();

            if (
                $state->last_checked_at &&
                $state->last_checked_at->diffInSeconds($now) <
                    self::MIN_CHECK_INTERVAL_SECONDS
            ) {
                $result['unchanged']++;

                return;
            }

            $activeAlert = DemandAlert::query()
                ->where('establishment_id', $establishment->id)
                ->where('type', $type)
                ->where('status', DemandAlert::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            $state->last_checked_at = $now;
            $state->last_measured_value = $value;

            if ($value >= self::START_PERCENT) {
                $this->handleHighDemand(
                    $state,
                    $activeAlert,
                    $establishment,
                    $type,
                    $value,
                    $unit,
                    $details,
                    $result,
                    $now
                );
            } elseif ($value < self::END_PERCENT) {
                $this->handleNormalDemand(
                    $state,
                    $activeAlert,
                    $value,
                    $details,
                    $result,
                    $now
                );
            } else {
                if ($activeAlert) {
                    $state->condition =
                        DemandAlertState::CONDITION_ACTIVE;
                } else {
                    $state->condition =
                        DemandAlertState::CONDITION_NORMAL;
                }

                $state->consecutive_checks = 0;
                $state->first_detected_at = null;

                $result['unchanged']++;
            }

            $state->save();
        }, 5);
    }

    private function handleHighDemand(
        DemandAlertState $state,
        ?DemandAlert $activeAlert,
        Establishment $establishment,
        string $type,
        float $value,
        string $unit,
        array $details,
        array &$result,
        $now
    ): void {
        $state->last_detected_at = $now;

        $level = $value >= 100
            ? DemandAlert::LEVEL_CRITICAL
            : DemandAlert::LEVEL_WARNING;

        if ($activeAlert) {
            $state->condition = DemandAlertState::CONDITION_ACTIVE;
            $state->consecutive_checks = 0;
            $state->first_detected_at = null;

            $activeAlert->update([
                'level' => $level,
                'message' => $this->message($type, $value),
                'measured_value' => $value,
                'details' => $details,
            ]);

            $result['unchanged']++;

            return;
        }

        if ($state->condition !== DemandAlertState::CONDITION_PENDING) {
            $state->condition = DemandAlertState::CONDITION_PENDING;
            $state->consecutive_checks = 1;
            $state->first_detected_at = $now;
        } else {
            $state->consecutive_checks++;
        }

        if ($state->consecutive_checks < self::REQUIRED_CHECKS) {
            $result['unchanged']++;

            return;
        }

        $alert = DemandAlert::create([
            'establishment_id' => $establishment->id,
            'type' => $type,
            'level' => $level,
            'status' => DemandAlert::STATUS_ACTIVE,
            'title' => $this->title($type),
            'message' => $this->message($type, $value),
            'measured_value' => $value,
            'threshold_value' => self::START_PERCENT,
            'unit' => $unit,
            'details' => $details,
            'started_at' => $now,
        ]);

        $state->condition = DemandAlertState::CONDITION_ACTIVE;
        $state->consecutive_checks = 0;
        $state->first_detected_at = null;

        $result['created']++;

        $alertId = $alert->id;

        DB::afterCommit(function () use ($alertId) {
            $this->notifyNewAlert($alertId);
        });
    }

    private function handleNormalDemand(
        DemandAlertState $state,
        ?DemandAlert $activeAlert,
        float $value,
        array $details,
        array &$result,
        $now
    ): void {
        if (! $activeAlert) {
            $state->condition = DemandAlertState::CONDITION_NORMAL;
            $state->consecutive_checks = 0;
            $state->first_detected_at = null;

            $result['unchanged']++;

            return;
        }

        if ($state->condition !== DemandAlertState::CONDITION_RECOVERING) {
            $state->condition = DemandAlertState::CONDITION_RECOVERING;
            $state->consecutive_checks = 1;
            $state->first_detected_at = $now;
        } else {
            $state->consecutive_checks++;
        }

        if ($state->consecutive_checks < self::REQUIRED_CHECKS) {
            $result['unchanged']++;

            return;
        }

        $activeAlert->update([
            'status' => DemandAlert::STATUS_RESOLVED,
            'measured_value' => $value,
            'details' => $details,
            'ended_at' => $now,
        ]);

        $state->condition = DemandAlertState::CONDITION_NORMAL;
        $state->consecutive_checks = 0;
        $state->first_detected_at = null;

        $result['resolved']++;
    }

    private function notifyNewAlert(int $alertId): void
    {
        try {
            $alert = DemandAlert::query()
                ->with('establishment.user')
                ->find($alertId);

            if (! $alert) {
                return;
            }

            $manager = $alert->establishment?->user;

            if (! $manager || ! $manager->isGestor()) {
                return;
            }

            $result = app(WebPushService::class)->sendToUser(
                $manager,
                'GoodWe - ' . $alert->title,
                $alert->message,
                '/gestor/demanda'
            );

            Log::info('GoodWe: notificação de demanda processada.', [
                'alert_id' => $alert->id,
                'establishment_id' => $alert->establishment_id,
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'removed' => $result['removed'],
            ]);
        } catch (Throwable $exception) {
            Log::error('GoodWe: falha na notificação de pico.', [
                'alert_id' => $alertId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function title(string $type): string
    {
        return $type === DemandAlert::TYPE_CHARGING_PEAK
            ? 'Pico de recargas detectado'
            : 'Alta demanda de energia detectada';
    }

    private function message(string $type, float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        if ($type === DemandAlert::TYPE_CHARGING_PEAK) {
            return "A ocupação dos carregadores atingiu {$formatted}%.";
        }

        return "A demanda elétrica atingiu {$formatted}% da capacidade configurada.";
    }
}
