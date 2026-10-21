<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava as recargas feitas na tela de Totem (Firestore) na tabela
 * `charging_sessions`, para que apareçam no dashboard do gestor.
 *
 * O totem é identificado pelo `id` do documento na coleção `totems` do
 * Firestore, que é o mesmo valor do `code` do carregador cadastrado pelo
 * gestor (ex.: `totem_01`). Essa é a ponte que atribui a recarga ao
 * estabelecimento — quando não existe carregador cadastrado, a sessão é
 * gravada sem estabelecimento e não aparece em nenhum painel de gestor.
 */
class TotemSessionService
{
    /** Recarga autorizada no totem (comando_web = iniciar) aguardando o PIN. */
    public const STATE_PENDING_PIN = 'pending_pin';

    /** Recarga autorizada e liberada pelo PIN no totem (comando_web = carregando). */
    public const STATE_CHARGING = 'charging';

    /** Recarga encerrada (comando_web = parar). */
    public const STATE_ENDED = 'ended';

    /**
     * Registra a recarga no banco assim que o comando de início é enviado ao
     * totem: o gestor passa a ver a sessão em andamento na lista de recargas
     * recentes, antes mesmo da confirmação do PIN.
     *
     * Não propaga falhas do banco: o comando já foi entregue ao equipamento e
     * um erro no espelho local não pode interromper a recarga do usuário.
     */
    public function start(User $user, string $totemId, float $tariffPerKwh): ?ChargingSession
    {
        $session = ChargingSession::where('external_session_id', $this->externalSessionId($totemId))
            ->whereIn('charging_state', [self::STATE_PENDING_PIN, self::STATE_CHARGING])
            ->latest('id')
            ->first();

        // Reenvio do comando para o mesmo totem: reaproveita a sessão aberta.
        if ($session !== null) {
            return $session;
        }

        try {
            $charger = $this->chargerForTotem($totemId);

            return ChargingSession::create([
                'user_id' => $user->id,
                'establishment_id' => $charger?->establishment_id,
                'charger_id' => $charger?->id,
                'external_session_id' => $this->externalSessionId($totemId),
                'charger_name' => $this->chargerName($totemId, $charger),
                'tariff_per_kwh' => $tariffPerKwh,
                'energy_kwh' => 0,
                'measured_energy_kwh' => '0.0000',
                'amount_charged' => 0,
                'original_amount' => 0,
                'discount_amount' => 0,
                'status' => ChargingSession::STATUS_PENDING,
                'charging_state' => self::STATE_PENDING_PIN,
                'payment_status' => 'not_due',
                'payment_method' => ChargingSession::PAYMENT_WALLET,
                'started_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Atualiza a sessão conforme o totem avança no fluxo (PIN validado pelo
     * ESP => `carregando`).
     */
    public function syncState(User $user, string $totemId, string $command): void
    {
        if ($command !== 'carregando') {
            return;
        }

        try {
            $this->openSession($user, $totemId)?->update([
                'charging_state' => self::STATE_CHARGING,
                'started_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Encerra a recarga lendo do documento do totem a energia consumida e o
     * valor medido pelo equipamento (`kwh`/`energia_kwh`/`energia` e
     * `valor`/`valor_total`). Sem os campos do totem, a energia e o valor são
     * calculados pela tarifa registrada na abertura da sessão.
     *
     * @param  array<string, mixed>  $totem
     */
    public function finish(User $user, string $totemId, array $totem): ?ChargingSession
    {
        try {
            $session = $this->openSession($user, $totemId);

            if ($session === null) {
                return null;
            }

            $measured = $this->measuredEnergy($totem);
            $amount = $this->reportedAmount($totem);

            if ($amount === null && $measured !== null && $session->tariff_per_kwh !== null) {
                $amount = EnergyBilling::amount($measured, (string) $session->tariff_per_kwh);
            }

            return DB::transaction(function () use ($session, $measured, $amount) {
                $session->update([
                    'charging_state' => self::STATE_ENDED,
                    'ended_at' => now(),
                    'measured_energy_kwh' => $measured ?? $session->measured_energy_kwh,
                    'energy_kwh' => $measured !== null ? round((float) $measured, 2) : $session->energy_kwh,
                    'original_amount' => $amount ?? $session->original_amount,
                    'amount_charged' => $amount ?? $session->amount_charged,
                    // A recarga terminou: entra no histórico e nos indicadores
                    // do gestor; o pagamento continua pendente na carteira.
                    'status' => ChargingSession::STATUS_COMPLETED,
                    'payment_status' => 'pending',
                ]);

                return $session->refresh();
            });
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Sessão de recarga aberta do usuário neste totem (recarga em andamento).
     */
    private function openSession(User $user, string $totemId): ?ChargingSession
    {
        return ChargingSession::where('user_id', $user->id)
            ->where('external_session_id', $this->externalSessionId($totemId))
            ->whereIn('charging_state', [self::STATE_PENDING_PIN, self::STATE_CHARGING])
            ->latest('id')
            ->first();
    }

    /**
     * Carregador cadastrado cujo código é o id do totem no Firestore.
     */
    private function chargerForTotem(string $totemId): ?Charger
    {
        return Charger::where('code', $totemId)->where('is_demo', false)->first();
    }

    private function chargerName(string $totemId, ?Charger $charger): string
    {
        if ($charger !== null) {
            return $charger->name;
        }

        $configured = collect(config('totems.available', []))->firstWhere('id', $totemId);
        $name = is_array($configured) ? ($configured['name'] ?? null) : null;

        return filled($name) ? (string) $name : $totemId;
    }

    /**
     * Identificador estável da recarga: a unicidade é por totem e usuário, o
     * que impede sessões duplicadas quando o usuário reenvia o comando.
     */
    private function externalSessionId(string $totemId): string
    {
        return Str::limit("totem:{$totemId}:web", 128, '');
    }

    /**
     * Energia consumida reportada pelo totem, em kWh.
     *
     * @param  array<string, mixed>  $totem
     */
    private function measuredEnergy(array $totem): ?string
    {
        foreach (['energia_kwh', 'energia', 'kwh'] as $field) {
            if (isset($totem[$field]) && is_numeric($totem[$field])) {
                return $this->normalizeDecimal((float) $totem[$field]);
            }
        }

        return null;
    }

    /**
     * Valor cobrado pela recarga conforme medido pelo totem, em reais.
     *
     * @param  array<string, mixed>  $totem
     */
    private function reportedAmount(array $totem): ?string
    {
        foreach (['valor_total', 'valor', 'valor_cobrado'] as $field) {
            if (isset($totem[$field]) && is_numeric($totem[$field])) {
                return $this->normalizeDecimal((float) $totem[$field]);
            }
        }

        return null;
    }

    /**
     * Formata um valor do totem com as quatro casas decimais aceitas pela
     * coluna decimal do banco.
     */
    private function normalizeDecimal(float $value): string
    {
        return number_format(max(0, $value), 4, '.', '');
    }
}
