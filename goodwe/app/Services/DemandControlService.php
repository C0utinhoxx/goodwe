<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\DemandReading;
use App\Models\Establishment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controle de demanda elétrica dos carregadores de um estabelecimento.
 *
 * Agrega o consumo total (potência ativa em kW) e a corrente ativa (A) de
 * todos os carregadores em recarga (EM_ANDAMENTO) a partir das leituras
 * reportadas pelo IoT/Firestore, e impede que novas recargas sejam autorizadas
 * quando a demanda agregada ultrapassaria a capacidade configurada da
 * infraestrutura elétrica local.
 */
class DemandControlService
{
    /** Estados de sessão que representam uma recarga em andamento (EM_ANDAMENTO). */
    public const ACTIVE_CHARGING_STATES = ['charging', 'stop_requested'];

    /**
     * Registra uma leitura de demanda de um carregador, de forma idempotente
     * (repetições com o mesmo identificador e conteúdo são aceitas; conteúdo
     * divergente é rejeitado).
     */
    public function recordReading(Establishment $establishment, Charger $charger, array $data): DemandReading
    {
        $externalId = (string) ($data['external_id'] ?? '');
        if ($externalId === '' || strlen($externalId) > 128) {
            throw ValidationException::withMessages(['external_id' => 'Identificador da leitura inválido.']);
        }
        if ($charger->establishment_id !== $establishment->id) {
            throw ValidationException::withMessages(['charger' => 'Carregador não pertence a este estabelecimento.']);
        }

        $recordedAt = $data['recorded_at'] instanceof CarbonInterface ? $data['recorded_at'] : now();
        if ($recordedAt->isFuture()) {
            throw ValidationException::withMessages(['recorded_at' => 'A leitura não pode estar no futuro.']);
        }

        $powerKw = $this->normalizeNonNegative($data['active_power_kw'] ?? 0, 'active_power_kw');
        $currentA = $this->normalizeNonNegative($data['current_a'] ?? null, 'current_a')
            ?? round(self::estimateCurrentA($powerKw, $data['voltage_v'] ?? null), 3);

        return DB::transaction(function () use ($establishment, $charger, $data, $externalId, $recordedAt, $powerKw, $currentA) {
            $existing = DemandReading::where('establishment_id', $establishment->id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                // Compara numericamente: o banco persiste decimal(3) e o valor
                // recebido é normalizado para float, então a comparação textual
                // divergiria ("10.500" !== "10.5").
                $diverges = abs((float) $existing->active_power_kw - $powerKw) > 0.0005
                    || abs((float) $existing->current_a - $currentA) > 0.0005;
                if ($diverges) {
                    throw ValidationException::withMessages(['external_id' => 'Leitura repetida com dados divergentes.']);
                }

                return $existing;
            }

            return DemandReading::create([
                'establishment_id' => $establishment->id,
                'charger_id' => $charger->id,
                'external_id' => $externalId,
                'active_power_kw' => $powerKw,
                'current_a' => $currentA,
                'voltage_v' => $data['voltage_v'] ?? null,
                'recorded_at' => $recordedAt,
                'source' => $data['source'] ?? DemandReading::SOURCE_IOT,
                'metadata' => $data['metadata'] ?? null,
            ]);
        }, 5);
    }

    /**
     * Lê os documentos dos totens no Firestore e registra uma leitura para
     * cada carregador que reportar consumo. O FirestoreService é resolvido de
     * forma tardia para não exigir credenciais do Firebase em fluxos que não
     * sincronizam.
     */
    public function syncReadingsFromFirestore(Establishment $establishment): int
    {
        $firestore = app(FirestoreService::class);
        $count = 0;

        foreach ($establishment->chargers()->where('is_demo', false)->get() as $charger) {
            $totem = $firestore->getDocumentData('totems', $charger->code);
            if ($totem === null || $totem === []) {
                continue;
            }

            $powerKw = $totem['consumo_kw'] ?? $totem['active_power_kw'] ?? null;
            $currentA = $totem['corrente_a'] ?? $totem['current_a'] ?? null;
            if ($powerKw === null && $currentA === null) {
                continue;
            }

            $recordedAt = isset($totem['medido_em'])
                ? Carbon::parse($totem['medido_em'])
                : now();

            $this->recordReading($establishment, $charger, [
                'external_id' => 'firestore:'.$charger->code.':'.$recordedAt->getTimestamp(),
                'active_power_kw' => $powerKw ?? 0,
                'current_a' => $currentA,
                'voltage_v' => $totem['tensao_v'] ?? $totem['voltage_v'] ?? null,
                'recorded_at' => $recordedAt,
                'source' => DemandReading::SOURCE_FIRESTORE,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Sessões em recarga (EM_ANDAMENTO) do estabelecimento.
     */
    public function activeSessions(Establishment $establishment): Collection
    {
        return ChargingSession::where('establishment_id', $establishment->id)
            ->whereIn('charging_state', self::ACTIVE_CHARGING_STATES)
            ->with('charger')
            ->get();
    }

    /**
     * Leitura mais recente de cada carregador dentro da janela de vigência.
     *
     * @return Collection<int, DemandReading>
     */
    public function latestReadingsByCharger(Establishment $establishment, ?CarbonInterface $since = null): Collection
    {
        $since ??= now()->subMinutes((int) config('demand.readings_window_minutes', 2));

        return DemandReading::where('establishment_id', $establishment->id)
            ->where('recorded_at', '>=', $since)
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('charger_id')
            ->values();
    }

    /**
     * Leituras vigentes dos carregadores que estão efetivamente em recarga.
     *
     * @return Collection<int, DemandReading>
     */
    public function activeReadings(Establishment $establishment): Collection
    {
        $activeChargerIds = $this->activeSessions($establishment)
            ->pluck('charger_id')
            ->filter()
            ->all();

        return $this->latestReadingsByCharger($establishment)
            ->filter(fn (DemandReading $reading) => $reading->charger_id !== null && in_array($reading->charger_id, $activeChargerIds, true))
            ->values();
    }

    public function totalActivePowerKw(Establishment $establishment): float
    {
        return round((float) $this->activeReadings($establishment)->sum(fn (DemandReading $reading) => (float) $reading->active_power_kw), 3);
    }

    public function totalActiveCurrentA(Establishment $establishment): float
    {
        return round((float) $this->activeReadings($establishment)->sum(fn (DemandReading $reading) => (float) $reading->current_a), 3);
    }

    /**
     * Verifica se autorizar uma nova recarga no carregador mantém a demanda
     * agregada dentro da capacidade configurada. Lança ValidationException
     * quando a capacidade seria excedida.
     */
    public function assertCanStartSession(Establishment $establishment, Charger $charger): void
    {
        if (! $establishment->hasDemandLimit()) {
            return;
        }

        $activeReadings = $this->activeReadings($establishment)
            ->filter(fn (DemandReading $reading) => $reading->charger_id !== $charger->id);

        $totalPowerKw = (float) $activeReadings->sum(fn (DemandReading $reading) => (float) $reading->active_power_kw);
        $totalCurrentA = (float) $activeReadings->sum(fn (DemandReading $reading) => (float) $reading->current_a);

        $chargerPowerKw = (float) $charger->power_kw;
        $chargerCurrentA = self::estimateCurrentA($chargerPowerKw);

        $exceedsPower = $establishment->max_power_kw !== null
            && ($totalPowerKw + $chargerPowerKw) > (float) $establishment->max_power_kw;
        $exceedsCurrent = $establishment->max_current_a !== null
            && ($totalCurrentA + $chargerCurrentA) > (float) $establishment->max_current_a;

        if ($exceedsPower || $exceedsCurrent) {
            throw ValidationException::withMessages([
                'charger' => 'A demanda ativa dos carregadores em recarga mais este equipamento excederia a capacidade elétrica configurada. Aguarde uma sessão encerrar.',
            ]);
        }
    }

    /**
     * Instantâneo da demanda atual para exibição no painel do gestor.
     *
     * @return array<string, mixed>
     */
    public function status(Establishment $establishment): array
    {
        $activeReadings = $this->activeReadings($establishment);
        $sessions = $this->activeSessions($establishment);

        $totalPowerKw = round((float) $activeReadings->sum(fn (DemandReading $reading) => (float) $reading->active_power_kw), 3);
        $totalCurrentA = round((float) $activeReadings->sum(fn (DemandReading $reading) => (float) $reading->current_a), 3);

        $maxPowerKw = $establishment->max_power_kw !== null ? (float) $establishment->max_power_kw : null;
        $maxCurrentA = $establishment->max_current_a !== null ? (float) $establishment->max_current_a : null;

        $overload = ($maxPowerKw !== null && $totalPowerKw > $maxPowerKw)
            || ($maxCurrentA !== null && $totalCurrentA > $maxCurrentA);

        return [
            'total_active_power_kw' => $totalPowerKw,
            'total_active_current_a' => $totalCurrentA,
            'max_power_kw' => $maxPowerKw,
            'max_current_a' => $maxCurrentA,
            'power_headroom_kw' => $maxPowerKw !== null ? round($maxPowerKw - $totalPowerKw, 3) : null,
            'current_headroom_a' => $maxCurrentA !== null ? round($maxCurrentA - $totalCurrentA, 3) : null,
            'overload' => $overload,
            'limit_configured' => $establishment->hasDemandLimit(),
            'active_sessions_count' => $sessions->count(),
            'readings' => $activeReadings->map(fn (DemandReading $reading) => [
                'charger_id' => $reading->charger_id,
                'charger_name' => $reading->charger?->name,
                'active_power_kw' => (float) $reading->active_power_kw,
                'current_a' => (float) $reading->current_a,
                'voltage_v' => $reading->voltage_v !== null ? (float) $reading->voltage_v : null,
                'recorded_at' => $reading->recorded_at?->toIso8601String(),
                'source' => $reading->source,
            ])->values(),
            'active_sessions' => $sessions->map(fn (ChargingSession $session) => [
                'id' => $session->id,
                'charger_id' => $session->charger_id,
                'charger_name' => $session->charger_name,
                'user_name' => $session->user?->name,
                'measured_energy_kwh' => $session->measured_energy_kwh !== null ? (float) $session->measured_energy_kwh : null,
                'started_at' => $session->started_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * Estima a corrente ativa (A) de um carregador a partir da sua potência
     * nominal (kW), tensão nominal e número de fases configurados.
     */
    public static function estimateCurrentA(float $powerKw, ?float $voltageV = null): float
    {
        $voltage = $voltageV ?? (float) config('demand.voltage_v', 220);
        if ($voltage <= 0 || $powerKw <= 0) {
            return 0.0;
        }

        $phases = max(1, (int) config('demand.phases', 3));
        $divisor = $phases > 1 ? sqrt($phases) * $voltage : $voltage;

        return round(($powerKw * 1000) / $divisor, 3);
    }

    private function normalizeNonNegative(mixed $value, string $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric < 0) {
            throw ValidationException::withMessages([$field => 'O valor não pode ser negativo.']);
        }

        return round($numeric, 3);
    }
}
