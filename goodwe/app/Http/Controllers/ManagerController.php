<?php

namespace App\Http\Controllers;

use App\Services\DemandPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManagerController extends Controller
{
    public function dashboard(Request $request, DemandPricingService $pricing): View
    {
        $establishment = $request->user()->establishment;
        $establishment->load('activePeak');
        $period = $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }
        $periodSince = now()->subDays($period);
        $sessions = $establishment->chargingSessions()->getQuery();
        $paid = (clone $sessions)->where('status', 'completed')->whereHas('transaction', fn ($query) => $query->where('type', 'charge_payment')->where('status', 'approved'));
        $consumed = (clone $sessions)->where('status', '!=', 'cancelled');
        $totalSessions = (clone $sessions)->count();
        $totalRevenue = (clone $paid)->sum('amount_charged');
        $totalEnergy = (clone $consumed)->sum(DB::raw('COALESCE(measured_energy_kwh, energy_kwh)'));
        $paidEnergy = (clone $paid)->sum(DB::raw('COALESCE(measured_energy_kwh, energy_kwh)'));
        $avgCostPerKwh = $paidEnergy > 0 ? $totalRevenue / $paidEnergy : 0;
        $periodSessionsCount = (clone $sessions)->where('created_at', '>=', $periodSince)->count();
        $periodRevenue = (clone $paid)->where('created_at', '>=', $periodSince)->sum('amount_charged');
        $periodEnergy = (clone $consumed)->where('created_at', '>=', $periodSince)->sum(DB::raw('COALESCE(measured_energy_kwh, energy_kwh)'));
        $periodActiveUsers = (clone $sessions)->where('created_at', '>=', $periodSince)->distinct('user_id')->count('user_id');
        $totalUsers = (clone $sessions)->distinct('user_id')->count('user_id');
        $energyDays = (clone $consumed)->where('created_at', '>=', $periodSince)
            ->selectRaw('DATE(created_at) as date, SUM(COALESCE(measured_energy_kwh, energy_kwh)) as total_kwh')->groupBy('date')->orderBy('date')->pluck('total_kwh', 'date');
        $revenueDays = (clone $paid)->where('created_at', '>=', $periodSince)
            ->selectRaw('DATE(created_at) as date, SUM(amount_charged) as total_revenue')->groupBy('date')->orderBy('date')->pluck('total_revenue', 'date');
        $dates = $energyDays->keys()->merge($revenueDays->keys())->unique()->sort()->values();
        $dailyLabels = $dates->map(fn ($date) => Carbon::parse($date)->format('d/m'));
        $dailyKwh = $dates->map(fn ($date) => $energyDays->get($date, 0));
        $dailyRevenue = $dates->map(fn ($date) => $revenueDays->get($date, 0));
        $chargerDistribution = (clone $sessions)->where('created_at', '>=', $periodSince)->where('status', 'completed')->select('charger_name', DB::raw('COUNT(*) as total'))->groupBy('charger_name')->orderByDesc('total')->get();
        $statusDistribution = (clone $sessions)->where('created_at', '>=', $periodSince)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get();
        $topUsers = (clone $paid)->where('created_at', '>=', $periodSince)->select('user_id', DB::raw('COUNT(*) as sessions_count'), DB::raw('SUM(COALESCE(measured_energy_kwh, energy_kwh)) as total_kwh'), DB::raw('SUM(amount_charged) as total_spent'))->groupBy('user_id')->orderByDesc('total_spent')->take(5)->with('user:id,name,email')->get();
        $recentSessions = (clone $sessions)->with('user:id,name')->latest()->take(10)->get();
        $liveChargers = $establishment->chargers()->orderBy('name')->get()->map(fn ($charger) => [
            'id' => $charger->code, 'name' => $charger->name, 'location' => $charger->location, 'power_kw' => $charger->power_kw,
            'connector' => $charger->connector, 'status' => $charger->status(), 'is_available' => $charger->isAvailable(),
        ]);
        $chargerCounts = ['total' => $liveChargers->count()];
        foreach (['available', 'reserved', 'in_use', 'unavailable'] as $status) {
            $chargerCounts[$status] = $liveChargers->where('status', $status)->count();
        }
        $detectorConfigured = $pricing->detectorConfigured();

        return view('manager.dashboard', compact('establishment', 'period', 'totalSessions', 'totalRevenue', 'totalEnergy', 'avgCostPerKwh', 'totalUsers', 'periodSessionsCount', 'periodRevenue', 'periodEnergy', 'periodActiveUsers', 'dailyLabels', 'dailyKwh', 'dailyRevenue', 'chargerDistribution', 'statusDistribution', 'topUsers', 'recentSessions', 'liveChargers', 'chargerCounts', 'detectorConfigured'));
    }

    public function exportSessions(Request $request)
    {
        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $sessions = $request->user()->establishment->chargingSessions()->with('user:id,name,email')
            ->where('created_at', '>=', now()->subDays($period))
            ->latest()
            ->get();

        $filename = 'sessoes-goodwe-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sessions) {
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
