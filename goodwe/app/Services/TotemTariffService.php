<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\Establishment;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Espelha no Firestore a tarifa vigente cadastrada no painel do gestor.
 *
 * O documento `totems/{code}` é o canal lido pelo ESP32 e pela tela de
 * recarga. Sempre que a tarifa efetiva de um carregador muda (tarifa-base,
 * reajuste de pico ou tarifa própria do equipamento), `tarifa_kwh` precisa
 * receber o novo valor para valer na próxima recarga.
 *
 * Falhas do Firestore não interrompem a operação do gestor: a tarifa local
 * continua sendo a fonte de cobrança e a próxima alteração tenta novamente.
 */
class TotemTariffService
{
    public function __construct(private readonly FirestoreService $firestore) {}

    /**
     * Sincroniza a tarifa vigente de todos os carregadores do estabelecimento.
     *
     * @return int Quantidade de totens atualizados.
     */
    public function syncEstablishment(Establishment $establishment): int
    {
        $chargers = $establishment->chargers()
            ->where('is_demo', false)
            ->get();

        $updated = 0;

        // First, sync chargers that have establishment relationship
        foreach ($chargers as $charger) {
            $charger->load('establishment');

            if ($this->syncCharger($charger)) {
                $updated++;
            }
        }

        // Then, sync ALL totems in Firebase to ensure they all have
        // the establishment's current tariff, even if they don't have
        // a corresponding charger model in the database.
        $allTotems = $this->firestore->listCollection('totems', 1000);
        foreach ($allTotems as $totem) {
            $totemId = $totem['id'] ?? null;

            if ($totemId === null) {
                continue;
            }

            // Get the tariff from the establishment (cost_per_kwh or peak_tariff)
            $tariff = $establishment->currentTariff();

            if ($tariff !== null && (float) $tariff > 0) {
                $this->firestore->setDocumentMerge('totems', $totemId, [
                    'tarifa_kwh' => (float) $tariff,
                ]);
                $updated++;
            }
        }

        info("TotemTariffService: Sync completed, {$updated} totems updated");

        return $updated;
    }

    /**
     * Grava a tarifa efetiva do carregador em `totems/{code}.tarifa_kwh`.
     */
    public function syncCharger(Charger $charger): bool
    {
        // A relação do estabelecimento já foi carregada em syncEstablishment
        // Mas carregar novamente para garantir dados atualizados
        $charger->load('establishment');

        $tariff = $charger->currentTariff();

        if ($tariff === null || (float) $tariff <= 0) {
            // Log para debug: tarifa inválida, não sincroniza
            info("TotemTariffService: Tarifa inválida para charger {$charger->code}: {$tariff}");
            return false;
        }

        try {
            $result = $this->firestore->setDocumentMerge('totems', $charger->code, [
                'tarifa_kwh' => (float) $tariff,
            ]);

            // Log de sucesso para debug
            info("TotemTariffService: Sincronizada tarifa {$tariff} para charger {$charger->code}");

            // Invalidar cache após sincronização bem-sucedida
            $this->forgetCache($charger->code);

            return true;
        } catch (Throwable $e) {
            // Log detalhado do erro
            report($e);
            error("TotemTariffService: Erro ao sincronizar tarifa para charger {$charger->code}: " . $e->getMessage());

            return false;
        }
    }

    private function forgetCache(string $totemId): void
    {
        Cache::forget("totems:document:{$totemId}");
        Cache::forget("totems:status:{$totemId}");
    }
}
