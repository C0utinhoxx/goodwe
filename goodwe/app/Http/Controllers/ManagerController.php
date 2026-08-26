<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Establishment;
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

        $sessions = ChargingSession::where('establishment_id', $establishment->id);

        $totalSessions = $sessions->clone()->count();
        $totalRevenue = $sessions->clone()->where('status', 'completed')->sum('amount_charged');
        $totalEnergy = $sessions->clone()->where('status', 'completed')->sum('energy_kwh');
        $avgCostPerKwh = $totalEnergy > 0 ? $totalRevenue / $totalEnergy : 0;

        $dailyUsage = ChargingSession::where('establishment_id', $establishment->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
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

        $chargerDistribution = ChargingSession::where('establishment_id', $establishment->id)
            ->where('status', 'completed')
            ->select('charger_name', DB::raw('COUNT(*) as total'))
            ->groupBy('charger_name')
            ->get();

        $statusDistribution = ChargingSession::where('establishment_id', $establishment->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $recentSessions = ChargingSession::where('establishment_id', $establishment->id)
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();

        return view('manager.dashboard', compact(
            'establishment',
            'totalSessions',
            'totalRevenue',
            'totalEnergy',
            'avgCostPerKwh',
            'dailyLabels',
            'dailyKwh',
            'dailyRevenue',
            'chargerDistribution',
            'statusDistribution',
            'recentSessions'
        ));
    }
}
