<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\Establishment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TotemTariffService
{
    public function __construct(
        private readonly FirestoreService $firestore
    ) {}

    public function syncEstablishment(Establishment $establishment): int
    {
        $chargers = $establishment->chargers()
            ->where('is_demo', false)
            ->get();

        $updated = 0;

        foreach ($chargers as $charger) {
            if ($this->syncCharger($charger)) {
                $updated++;
            }
        }

        Log::info(
            'TotemTariffService: Sincronizacao concluida.',
            [
                'establishment_id' => $establishment->id,
                'totens_atualizados' => $updated,
            ]
        );

        return $updated;
    }

    public function syncCharger(Charger $charger): bool
    {
        $charger->load('establishment');

        $tariff = $charger->currentTariff();

        if ($tariff === null || (float) $tariff <= 0) {
            Log::warning(
                'TotemTariffService: Tarifa invalida.',
                [
                    'charger' => $charger->code,
                    'tarifa' => $tariff,
                ]
            );

            return false;
        }

        try {
            $this->firestore->setDocumentMerge(
                'totems',
                $charger->code,
                [
                    'tarifa_kwh' => (float) $tariff,
                ]
            );

            $this->forgetCache($charger->code);

            Log::info(
                'TotemTariffService: Tarifa sincronizada.',
                [
                    'charger' => $charger->code,
                    'establishment_id' => $charger->establishment_id,
                    'tarifa' => $tariff,
                ]
            );

            return true;
        } catch (Throwable $e) {
            report($e);

            Log::error(
                'TotemTariffService: Erro ao sincronizar tarifa.',
                [
                    'charger' => $charger->code,
                    'erro' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    private function forgetCache(string $totemId): void
    {
        Cache::forget("totems:document:{$totemId}");
        Cache::forget("totems:status:{$totemId}");
    }
}
