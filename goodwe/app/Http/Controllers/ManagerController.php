<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $establishment = $user->establishment;

        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        // As recargas de teste (simulacao pelo portal) aparecem na lista de
        // sessoes, mas so compoem os indicadores quando o ambiente permite.
        $includeTestSessions = app()->environment(['local', 'testing']);

        $establishmentSessions = ChargingSession::query()
            ->where('establishment_id', $establishment->id);

        // Agregados financeiros e de consumo ignoram recargas de teste fora de
        // ambientes de desenvolvimento.
        $aggregateSessions = (clone $establishmentSessions);
        if (! $includeTestSessions) {
            $aggregateSessions->whereNull('simulation');
        }

        // Receita = somente pagamentos confirmados (transacao aprovada ou
        // registro legado sem status de pagamento pendente).
        $revenueExpression = "CASE WHEN status = 'completed' AND (transaction_id IS NOT NULL OR payment_status IS NULL OR payment_status <> 'pending') THEN amount_charged ELSE 0 END";
        // Consumo = medida real quando registrada; senao, energia estimada de
        // sessoes concluidas.
        $energyExpression = "CASE WHEN measured_energy_kwh IS NOT NULL THEN measured_energy_kwh WHEN status = 'completed' THEN energy_kwh ELSE 0 END";

        $historicalTotals = (clone $aggregateSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_energy")
            ->first();

        $totalSessions = (int) $historicalTotals->sessions_count;
        $totalRevenue = (float) $historicalTotals->total_revenue;
        $totalEnergy = (float) $historicalTotals->total_energy;
        $avgCostPerKwh = $totalEnergy > 0 ? $totalRevenue / $totalEnergy : 0;
        $totalUsers = (clone $aggregateSessions)->distinct()->count('user_id');

        $periodSince = now()->subDays($period - 1)->startOfDay();
        $periodUntil = now()->endOfDay();
        $periodSessions = (clone $aggregateSessions)
            ->whereBetween('created_at', [$periodSince, $periodUntil]);

        $periodTotals = (clone $periodSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_energy")
            ->first();

        $periodSessionsCount = (int) $periodTotals->sessions_count;
        $periodRevenue = (float) $periodTotals->total_revenue;
        $periodEnergy = (float) $periodTotals->total_energy;
        $periodActiveUsers = (int) $periodTotals->active_users;
        $hasPeriodData = $periodSessionsCount > 0;

        $dailyUsage = (clone $periodSessions)
            ->select(DB::raw('DATE(created_at) as date'))
            ->selectRaw("COALESCE(SUM({$energyExpression}), 0) as total_kwh")
            ->selectRaw("COALESCE(SUM({$revenueExpression}), 0) as total_revenue")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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

        $chargerDistribution = (clone $periodSessions)
            ->select('charger_name', DB::raw('COUNT(*) as total'))
            ->groupBy('charger_name')
            ->orderByDesc('total')
            ->get();

        $statusTotals = (clone $periodSessions)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $detectorConfigured = (bool) config('demand.detector');

        // As recargas de teste (simulacao pelo portal) aparecem na lista de
        // sessoes, mas so compoem os indicadores quando o ambiente permite.
        $includeTestSessions = app()->environment(['local', 'testing']);

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

        $topUsers = (clone $periodSessions)
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

        $recentSessions = (clone $establishmentSessions)
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

        $sessions = $establishment
            ? ChargingSession::with('user:id,name,email')
                ->where('establishment_id', $establishment->id)
                ->when(! $includeTestSessions, fn ($query) => $query->whereNull('simulation'))
                ->whereBetween('created_at', [$periodSince, now()->endOfDay()])
                ->latest()
                ->get()
            : collect();

        $filename = 'sessoes-goodwe-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sessions): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Usuario', 'Email', 'Carregador', 'Origem', 'Energia (kWh)', 'Valor (R$)', 'Metodo', 'Status'], ',', '"', '\\');

            foreach ($sessions as $session) {
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
