<?php

namespace App\Services;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\DemandReading;
use App\Models\Establishment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
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

        ['power_kw' => $powerKw, 'current_a' => $currentA] = $this->normalizeReportedPowerAndCurrent($data);

        return DB::transaction(function () use ($establishment, $charger, $data, $externalId, $recordedAt, $powerKw, $currentA) {
            $existing = DemandReading::where('establishment_id', $establishment->id)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $this->assertReadingDoesNotDiverge($existing, $powerKw, $currentA);

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
     * Lê os documentos dos totens no Firestore e registra as leituras
     * reportadas em um único lote.
     *
     * O Firestore é consultado uma vez por carregador (chamadas externas, uma
     * após a outra), mas o banco é tocado com uma única transação: uma busca
     * das leituras já existentes + um único INSERT do lote — em vez de uma
     * transação por carregador como fazia a versão sequencial.
     */
    public function syncReadingsFromFirestore(Establishment $establishment): int
    {
        $firestore = app(FirestoreService::class);
        $entries = [];

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

            $normalized = $this->normalizeReportedPowerAndCurrent([
                'active_power_kw' => $powerKw ?? 0,
                'current_a' => $currentA,
                'voltage_v' => $totem['tensao_v'] ?? $totem['voltage_v'] ?? null,
            ]);

            $entries[] = [
                'charger' => $charger,
                'external_id' => 'firestore:'.$charger->code.':'.$recordedAt->getTimestamp(),
                'power_kw' => $normalized['power_kw'],
                'current_a' => $normalized['current_a'],
                'voltage_v' => $totem['tensao_v'] ?? $totem['voltage_v'] ?? null,
                'recorded_at' => $recordedAt,
            ];
        }

        if ($entries === []) {
            return 0;
        }

        return DB::transaction(fn (): int => $this->storeReadingsBatch($establishment, $entries), 5);
    }

    /**
     * Grava o lote de leituras de forma idempotente: leituras repetidas (mesmo
     * `external_id`) são reaproveitadas quando os valores batem e rejeitadas
     * quando divergem.
     *
     * @param  array<int, array{charger: Charger, external_id: string, power_kw: float, current_a: float, voltage_v: mixed, recorded_at: CarbonInterface}>  $entries
     */
    private function storeReadingsBatch(Establishment $establishment, array $entries): int
    {
        $existing = DemandReading::where('establishment_id', $establishment->id)
            ->whereIn('external_id', array_column($entries, 'external_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('external_id');

        $rows = [];
        foreach ($entries as $entry) {
            $duplicate = $existing->get($entry['external_id']);
            if ($duplicate !== null) {
                $this->assertReadingDoesNotDiverge($duplicate, $entry['power_kw'], $entry['current_a']);

                continue;
            }

            $rows[] = [
                'establishment_id' => $establishment->id,
                'charger_id' => $entry['charger']->id,
                'external_id' => $entry['external_id'],
                'active_power_kw' => $entry['power_kw'],
                'current_a' => $entry['current_a'],
                'voltage_v' => $entry['voltage_v'],
                'recorded_at' => $entry['recorded_at'],
                'source' => DemandReading::SOURCE_FIRESTORE,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DemandReading::insert($rows);
        }

        return count($entries);
    }

    /**
     * Leituras em recarga (EM_ANDAMENTO) do estabelecimento.
     */
    public function activeSessions(Establishment $establishment): Collection
    {
        return ChargingSession::where('establishment_id', $establishment->id)
            ->whereIn('charging_state', self::ACTIVE_CHARGING_STATES)
            ->with(['charger:id,name', 'user:id,name'])
            ->get();
    }

    /**
     * Leitura mais recente de cada carregador dentro da janela de vigência.
     *
     * A seleção da leitura mais recente por carregador acontece no banco
     * (GROUP BY + junção), evitando carregar todas as leituras da janela para
     * deduplicar em PHP.
     *
     * @return Collection<int, DemandReading>
     */
    public function latestReadingsByCharger(Establishment $establishment, ?CarbonInterface $since = null): Collection
    {
        $since ??= now()->subMinutes((int) config('demand.readings_window_minutes', 2));

        $latestPerCharger = DemandReading::query()
            ->selectRaw('charger_id, MAX(recorded_at) as latest_recorded_at')
            ->where('establishment_id', $establishment->id)
            ->where('recorded_at', '>=', $since)
            ->whereNotNull('charger_id')
            ->groupBy('charger_id');

        return DemandReading::query()
            ->joinSub($latestPerCharger, 'latest_reading', function (JoinClause $join): void {
                $join->on('latest_reading.charger_id', '=', 'demand_readings.charger_id')
                    ->on('latest_reading.latest_recorded_at', '=', 'demand_readings.recorded_at');
            })
            ->where('demand_readings.establishment_id', $establishment->id)
            ->select('demand_readings.*')
            ->with('charger:id,name')
            ->get()
            ->unique('charger_id')
            ->values();
    }

    /**
     * Leituras vigentes dos carregadores que estão efetivamente em recarga.
     *
     * @param  Collection<int, ChargingSession>|null  $activeSessions  Sessões já carregadas pelo chamador, para não consultar duas vezes.
     * @return Collection<int, DemandReading>
     */
    public function activeReadings(Establishment $establishment, ?Collection $activeSessions = null): Collection
    {
        $activeChargerIds = ($activeSessions ?? $this->activeSessions($establishment))
            ->pluck('charger_id')
            ->filter()
            ->all();

        return $this->latestReadingsByCharger($establishment)
            ->filter(fn (DemandReading $reading) => in_array($reading->charger_id, $activeChargerIds, true))
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
        // Uma única consulta de sessões alimenta as leituras ativas, o
        // contador e a listagem — antes cada bloco repetia a mesma consulta.
        $sessions = $this->activeSessions($establishment);
        $activeReadings = $this->activeReadings($establishment, $sessions);

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

    /**
     * Normaliza os valores elétricos reportados por um totem.
     *
     * @return array{power_kw: float, current_a: float}
     */
    private function normalizeReportedPowerAndCurrent(array $data): array
    {
        $powerKw = $this->normalizeNonNegative($data['active_power_kw'] ?? 0, 'active_power_kw');
        $currentA = $this->normalizeNonNegative($data['current_a'] ?? null, 'current_a')
            ?? round(self::estimateCurrentA($powerKw ?? 0.0, $data['voltage_v'] ?? null), 3);

        return ['power_kw' => $powerKw ?? 0.0, 'current_a' => $currentA ?? 0.0];
    }

    /**
     * Rejeita o reenvio de uma leitura já registrada com conteúdo divergente.
     *
     * A comparação é numérica: o banco persiste decimal(3) e o valor recebido
     * é normalizado para float, então a comparação textual divergiria
     * ("10.500" !== "10.5").
     */
    private function assertReadingDoesNotDiverge(DemandReading $reading, float $powerKw, float $currentA): void
    {
        $diverges = abs((float) $reading->active_power_kw - $powerKw) > 0.0005
            || abs((float) $reading->current_a - $currentA) > 0.0005;

        if ($diverges) {
            throw ValidationException::withMessages(['external_id' => 'Leitura repetida com dados divergentes.']);
        }
    }
}
