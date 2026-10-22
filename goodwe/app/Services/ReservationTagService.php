<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingReservation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mantém a variável `tag_reservada` do Firestore em sincronia com o ciclo
 * de vida da reserva.
 *
 * O flag é gravado nos dois documentos que o equipamento e o aplicativo
 * consultam — `totems/{code}` e `users/{tag}` — e o valor é recalculado a
 * partir do banco a cada transição (reserva iniciada, cancelada ou
 * expirada), para não apagar uma garantia vigente de outra reserva.
 *
 * Falhas do Firebase são registradas em log e não derrubam a reserva: o
 * cadastro no banco já foi confirmado (e a taxa, quando houver, já foi
 * cobrada) nesse ponto.
 */
class ReservationTagService
{
    public function sync(ChargingReservation $reservation): void
    {
        try {
            $charger = Charger::find($reservation->charger_id);
            $user = $reservation->user;

            if ($charger === null || $user === null) {
                return;
            }

            $firestore = app(FirestoreService::class);

            // Processos de teste que não recebem o fake em memória (ex.:
            // os processos paralelos dos testes de concorrência) não devem
            // tocar o Firebase real.
            if (app()->runningUnitTests() && $firestore::class === FirestoreService::class) {
                return;
            }

            $tag = filled($user->tag_uid) ? (string) $user->tag_uid : "WEB_USER_{$user->id}";

            $firestore->setDocumentsMerge([
                "totems/{$charger->code}" => [
                    'tag_reservada' => $charger->active_reservation_id !== null,
                ],
                "users/{$tag}" => [
                    'tag_reservada' => $this->userHasActiveReservation($reservation),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível sincronizar tag_reservada no Firestore.', [
                'reservation_id' => $reservation->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function userHasActiveReservation(ChargingReservation $reservation): bool
    {
        return ChargingReservation::query()
            ->where('user_id', $reservation->user_id)
            ->where('status', 'active')
            ->exists();
    }
}
