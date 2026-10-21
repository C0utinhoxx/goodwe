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

        foreach ($chargers as $charger) {
            // Evita uma consulta por carregador: a tarifa herdada vem do
            // estabelecimento que acabou de ser salvo.
            $charger->setRelation('establishment', $establishment);

            if ($this->syncCharger($charger)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Grava a tarifa efetiva do carregador em `totems/{code}.tarifa_kwh`.
     */
    public function syncCharger(Charger $charger): bool
    {
        $tariff = $charger->currentTariff();

        if ($tariff === null || (float) $tariff <= 0) {
            return false;
        }

        try {
            $this->firestore->setDocumentMerge('totems', $charger->code, [
                'tarifa_kwh' => (float) $tariff,
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            // A tela de recarga lê o documento com cache curto: sem a
            // invalidação o novo valor só apareceria depois do TTL.
            $this->forgetCache($charger->code);
        }
    }

    private function forgetCache(string $totemId): void
    {
        Cache::forget("totems:document:{$totemId}");
        Cache::forget("totems:status:{$totemId}");
    }
}
