<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Establishment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $establishment = $user->establishment;
        // O componente de resumo de preços lê o pico ativo do estabelecimento;
        // sem carregar aqui a view dispara uma consulta extras por request.
        $establishment->loadMissing('activePeak');

        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        // As recargas de teste (simulacao pelo portal) aparecem na lista de
        // sessoes, mas so compoem os indicadores quando o ambiente permite.
        $includeTestSessions = app()->environment(['local', 'testing']);

        $periodSince = now()->subDays($period - 1)->startOfDay();
        $periodUntil = now()->endOfDay();

        // Indicadores agregados são os custosos (varrem todas as sessões do
        // estabelecimento); ficam em cache por um minuto, separados por
        // período, por dia e por o ambiente compor ou não as recargas de teste.
        $buildMetrics = fn (): array => $this->dashboardMetrics(
            $establishment,
            [$periodSince, $periodUntil],
            $includeTestSessions
        );
        $cacheSeconds = (int) config('manager.dashboard_cache_seconds', 60);
        $metricsKey = sprintf(
            'manager:dashboard:%d:%s:%d:%s',
            $establishment->id,
            now()->toDateString(),
            $period,
            $includeTestSessions ? 'with-test' : 'real-only'
        );
        $metrics = $cacheSeconds > 0
            ? Cache::remember($metricsKey, $cacheSeconds, $buildMetrics)
            : $buildMetrics();

        $historicalTotals = $metrics['historicalTotals'];
        $totalSessions = (int) $historicalTotals->sessions_count;
        $totalUsers = (int) $historicalTotals->users_count;
        $totalRevenue = (float) $historicalTotals->total_revenue;
        $totalEnergy = (float) $historicalTotals->total_energy;
        $avgCostPerKwh = $totalEnergy > 0 ? $totalRevenue / $totalEnergy : 0;

        $periodTotals = $metrics['periodTotals'];
        $periodSessionsCount = (int) $periodTotals->sessions_count;
        $periodRevenue = (float) $periodTotals->total_revenue;
        $periodEnergy = (float) $periodTotals->total_energy;
        $periodActiveUsers = (int) $periodTotals->active_users;
        $hasPeriodData = $periodSessionsCount > 0;

        $dailyUsage = $metrics['dailyUsage'];
        $hasCompletedData = $dailyUsage->isNotEmpty();
        $dailyUsageByDate = $dailyUsage->keyBy('date');
        $periodDates = collect(range(0, $period - 1))
            ->map(fn (int $day) => $periodSince->copy()->addDays($day));
        $dailyLabels = $periodDates->map(fn (Carbon $date) => $date->format('d/m'));
        $dailyKwh = $periodDates->map(
            fn (Carbon $date) => (float) ($dailyUsageByDate->get($date->toDateString())?->total_kwh ?? 0)
        );
        $dailyRevenue = $periodDates->map(
            fn (Carbon $date) => (float) ($dailyUsageByDate->get($date->toDateString())?->total_revenue ?? 0)
        );

        $chargerDistribution = $metrics['chargerDistribution'];

        $statusTotals = $metrics['statusTotals'];

        $detectorConfigured = (bool) config('demand.detector');

        $chargerCounts = ['total' => 0, 'available' => 0, 'reserved' => 0, 'in_use' => 0, 'unavailable' => 0];
        foreach ($establishment->chargers as $charger) {
            $chargerCounts['total']++;
            $chargerCounts[$charger->status()]++;
        }

        $statusDistribution = collect([
            ChargingSession::STATUS_COMPLETED,
            ChargingSession::STATUS_PENDING,
            ChargingSession::STATUS_CANCELLED,
        ])->map(fn (string $status) => [
            'status' => $status,
            'total' => (int) ($statusTotals[$status] ?? 0),
        ]);

        $topUsers = $this->scopedSessionQuery($establishment, $includeTestSessions)
            ->whereBetween('created_at', [$periodSince, $periodUntil])
            ->select('user_id', DB::raw('COUNT(*) as sessions_count'))
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN energy_kwh ELSE 0 END), 0) as total_kwh',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount_charged ELSE 0 END), 0) as total_spent',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->groupBy('user_id')
            ->orderByDesc('total_spent')
            ->orderByDesc('sessions_count')
            ->take(5)
            ->with('user:id,name,email')
            ->get();

        $recentSessions = ChargingSession::query()
            ->where('establishment_id', $establishment->id)
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();

        // Lista de carregadores reais do estabelecimento (cadastro), nao o
        // simulador global: evita vazar equipamentos de outros CNPJs.
        $liveChargers = $establishment->chargers->map(fn ($charger) => [
            'id' => $charger->code ?? $charger->id,
            'name' => $charger->name,
            'location' => $charger->location ?? '—',
            'power_kw' => $charger->power_kw,
            'connector' => $charger->connector ?? '—',
            'status' => $charger->status(),
            'is_available' => $charger->isAvailable(),
        ]);

        return view('manager.dashboard', compact(
            'establishment',
            'period',
            'totalSessions',
            'totalRevenue',
            'totalEnergy',
            'avgCostPerKwh',
            'totalUsers',
            'periodSessionsCount',
            'periodRevenue',
            'periodEnergy',
            'periodActiveUsers',
            'hasPeriodData',
            'hasCompletedData',
            'dailyLabels',
            'dailyKwh',
            'dailyRevenue',
            'chargerCounts',
            'detectorConfigured',
            'includeTestSessions',
            'chargerDistribution',
            'statusDistribution',
            'topUsers',
            'recentSessions',
            'liveChargers'
        ));
    }

    /**
     * Sessões do estabelecimento, com ou sem as recargas de teste do portal.
     */
    private function scopedSessionQuery(Establishment $establishment, bool $includeTestSessions): Builder
    {
        return ChargingSession::query()
            ->where('establishment_id', $establishment->id)
            ->when(! $includeTestSessions, fn ($query) => $query->whereNull('simulation'));
    }

    /**
     * Indicadores agregados do painel (histórico, período, uso diário,
     * distribuição por carregador e por status).
     *
     * Todas as consultas varrem as sessões do estabelecimento — a parte
     * custosa do dashboard — por isso o chamador cacheia o resultado.
     *
     * @param  array{0: Carbon, 1: Carbon}  $periodRange
     * @return array{historicalTotals: object, periodTotals: object, dailyUsage: Collection, chargerDistribution: Collection, statusTotals: Collection}
     */
    private function dashboardMetrics(Establishment $establishment, array $periodRange, bool $includeTestSessions): array
    {
        [$periodSince, $periodUntil] = $periodRange;

        $aggregateSessions = $this->scopedSessionQuery($establishment, $includeTestSessions);

        // Receita = somente pagamentos confirmados (transacao aprovada ou
        // registro legado sem status de pagamento pendente).
        $revenueExpression = "CASE WHEN status = 'completed' AND (transaction_id IS NOT NULL OR payment_status IS NULL OR payment_status <> 'pending') THEN amount_charged ELSE 0 END";
        // Consumo = medida real quando registrada; senao, energia estimada de
        // sessoes concluidas.
        $energyExpression = "CASE WHEN measured_energy_kwh IS NOT NULL THEN measured_energy_kwh WHEN status = 'completed' THEN energy_kwh ELSE 0 END";

        // Histórico e usuários distintos em uma única varredura.
        $historicalTotals = (clone $aggregateSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COUNT(DISTINCT user_id) as users_count')
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_energy")
            ->first();

        $periodSessions = (clone $aggregateSessions)
            ->whereBetween('created_at', [$periodSince, $periodUntil]);

        $periodTotals = (clone $periodSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_energy")
            ->first();

        $dailyUsage = (clone $periodSessions)
            ->select(DB::raw('DATE(created_at) as date'))
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_kwh")
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $chargerDistribution = (clone $periodSessions)
            ->select('charger_name', DB::raw('COUNT(*) as total'))
            ->groupBy('charger_name')
            ->orderByDesc('total')
            ->get();

        $statusTotals = (clone $periodSessions)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return compact(
            'historicalTotals',
            'periodTotals',
            'dailyUsage',
            'chargerDistribution',
            'statusTotals'
        );
    }

    public function exportSessions(Request $request): StreamedResponse
    {
        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $establishment = $request->user()->establishment;
        $periodSince = now()->subDays($period - 1)->startOfDay();

        // Producao exporta apenas recargas reais; ambientes de teste tambem
        // incluem simulacoes, com a coluna Origem sinalizando cada linha.
        $includeTestSessions = app()->environment(['local', 'testing']);

        $sessionsQuery = $establishment
            ? ChargingSession::query()
                ->with('user:id,name,email')
                ->where('establishment_id', $establishment->id)
                ->when(! $includeTestSessions, fn ($query) => $query->whereNull('simulation'))
                ->whereBetween('created_at', [$periodSince, now()->endOfDay()])
            : null;

        $filename = 'sessoes-goodwe-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sessionsQuery): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Usuario', 'Email', 'Carregador', 'Origem', 'Energia (kWh)', 'Valor (R$)', 'Metodo', 'Status'], ',', '"', '\\');

            // Blocos de 500 linhas, da mais recente para a mais antiga: o uso
            // de memória fica constante mesmo exportando meses de sessões.
            foreach ($sessionsQuery?->lazyByIdDesc(500) ?? [] as $session) {
                fputcsv($handle, [
                    $session->created_at->format('d/m/Y H:i'),
                    $session->user->name ?? '-',
                    $session->user->email ?? '-',
                    $session->charger_name,
                    $session->isWebSimulation() ? 'Recarga de teste' : 'Recarga própria',
                    number_format((float) ($session->measured_energy_kwh ?? $session->energy_kwh), 2, ',', '.'),
                    number_format((float) $session->amount_charged, 2, ',', '.'),
                    $session->payment_method,
                    $session->status,
                ], ',', '"', '\\');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
