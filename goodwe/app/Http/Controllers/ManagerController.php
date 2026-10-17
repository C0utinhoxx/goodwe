<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Services\ChargingSimulator;
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

        if (! $establishment) {
            $establishment = Establishment::create([
                'user_id' => $user->id,
                'name' => 'Meu Estabelecimento',
                'address' => 'Endereco nao definido',
                'type' => Establishment::TYPE_COMERCIAL,
                'operating_hours' => '08:00 - 22:00',
                'cost_per_kwh' => 1.5000,
            ]);
        }

        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $establishmentSessions = ChargingSession::query()
            ->where('establishment_id', $establishment->id);

        $historicalTotals = (clone $establishmentSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount_charged ELSE 0 END), 0) as total_revenue',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN energy_kwh ELSE 0 END), 0) as total_energy',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->first();

        $totalSessions = (int) $historicalTotals->sessions_count;
        $totalRevenue = (float) $historicalTotals->total_revenue;
        $totalEnergy = (float) $historicalTotals->total_energy;
        $avgCostPerKwh = $totalEnergy > 0 ? $totalRevenue / $totalEnergy : 0;
        $totalUsers = (clone $establishmentSessions)->distinct()->count('user_id');

        $periodSince = now()->subDays($period - 1)->startOfDay();
        $periodUntil = now()->endOfDay();
        $periodSessions = (clone $establishmentSessions)
            ->whereBetween('created_at', [$periodSince, $periodUntil]);

        $periodTotals = (clone $periodSessions)
            ->selectRaw('COUNT(*) as sessions_count')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount_charged ELSE 0 END), 0) as total_revenue',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN energy_kwh ELSE 0 END), 0) as total_energy',
                [ChargingSession::STATUS_COMPLETED]
            )
            ->first();

        $periodSessionsCount = (int) $periodTotals->sessions_count;
        $periodRevenue = (float) $periodTotals->total_revenue;
        $periodEnergy = (float) $periodTotals->total_energy;
        $periodActiveUsers = (int) $periodTotals->active_users;
        $hasPeriodData = $periodSessionsCount > 0;

        $dailyUsage = (clone $periodSessions)
            ->where('status', ChargingSession::STATUS_COMPLETED)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(energy_kwh) as total_kwh'),
                DB::raw('SUM(amount_charged) as total_revenue')
            )
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

        $liveChargers = collect(ChargingSimulator::all())->map(fn ($charger) => [
            'id' => $charger->id,
            'name' => $charger->name,
            'location' => $charger->location,
            'power_kw' => $charger->powerKw,
            'connector' => $charger->connector,
            'status' => $charger->status(),
            'is_available' => $charger->isAvailable(),
            'vehicle_plate' => $charger->vehiclePlate,
            'available_in_minutes' => $charger->availableInMinutes(),
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

        $sessions = $establishment
            ? ChargingSession::with('user:id,name,email')
                ->where('establishment_id', $establishment->id)
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
            fputcsv($handle, ['Data', 'Usuario', 'Email', 'Carregador', 'Energia (kWh)', 'Valor (R$)', 'Metodo', 'Status'], ',', '"', '\\');

            foreach ($sessions as $session) {
                fputcsv($handle, [
                    $session->created_at->format('d/m/Y H:i'),
                    $session->user->name ?? '-',
                    $session->user->email ?? '-',
                    $session->charger_name,
                    number_format((float) $session->energy_kwh, 2, ',', '.'),
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
