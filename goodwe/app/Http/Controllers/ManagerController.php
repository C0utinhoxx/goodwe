<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Establishment;
use App\Models\User;
use App\Services\ChargingSimulator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    public function dashboard(Request $request)
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

        $sessions = ChargingSession::query();

        $totalSessions = $sessions->clone()->count();
        $totalRevenue = $sessions->clone()->where('status', 'completed')->sum('amount_charged');
        $totalEnergy = $sessions->clone()->where('status', 'completed')->sum('energy_kwh');
        $avgCostPerKwh = $totalEnergy > 0 ? $totalRevenue / $totalEnergy : 0;

        $periodSince = now()->subDays($period);

        $periodSessions = ChargingSession::where('created_at', '>=', $periodSince)
            ->where('status', 'completed');
        $periodSessionsCount = $periodSessions->clone()->count();
        $periodRevenue = $periodSessions->clone()->sum('amount_charged');
        $periodEnergy = $periodSessions->clone()->sum('energy_kwh');
        $periodActiveUsers = $periodSessions->clone()->distinct('user_id')->count('user_id');

        $totalUsers = User::count();

        $dailyUsage = ChargingSession::where('status', 'completed')
            ->where('created_at', '>=', $periodSince)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(energy_kwh) as total_kwh'),
                DB::raw('SUM(amount_charged) as total_revenue'),
                DB::raw('COUNT(*) as session_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyLabels = $dailyUsage->pluck('date')->map(fn ($d) => Carbon::parse($d)->format('d/m'));
        $dailyKwh = $dailyUsage->pluck('total_kwh');
        $dailyRevenue = $dailyUsage->pluck('total_revenue');

        $chargerDistribution = ChargingSession::where('status', 'completed')
            ->where('created_at', '>=', $periodSince)
            ->select('charger_name', DB::raw('COUNT(*) as total'))
            ->groupBy('charger_name')
            ->orderByDesc('total')
            ->get();

        $statusDistribution = ChargingSession::where('created_at', '>=', $periodSince)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $topUsers = ChargingSession::where('status', 'completed')
            ->where('created_at', '>=', $periodSince)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as sessions_count'),
                DB::raw('SUM(energy_kwh) as total_kwh'),
                DB::raw('SUM(amount_charged) as total_spent')
            )
            ->groupBy('user_id')
            ->orderByDesc('total_spent')
            ->take(5)
            ->with('user:id,name,email')
            ->get();

        $recentSessions = ChargingSession::with('user:id,name')
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

    public function exportSessions(Request $request)
    {
        $period = (int) $request->integer('period', 30);
        if (! in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $establishment = $request->user()->establishment;

        if (! $establishment) {
            abort(403, 'No establishment is associated with this account.');
        }

        $sessions = ChargingSession::with('user:id,name,email')
            ->where('establishment_id', $establishment->id)
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
