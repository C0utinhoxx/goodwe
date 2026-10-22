<?php

namespace App\Http\Controllers;

use App\Http\Requests\PricingDecisionRequest;
use App\Models\DemandAlert;
use App\Models\DemandPeak;
use App\Services\DemandPricingService;
use App\Services\TotemTariffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemandPricingController extends Controller
{
    public function index(
        Request $request,
        DemandPricingService $service
    ): View {
        $establishment = $request->user()->establishment;

        $establishment->load('activePeak');

        $events = $establishment
            ->tariffEvents()
            ->with('actor:id,name')
            ->latest('id')
            ->paginate(20);

        $activeAlerts = DemandAlert::query()
            ->where('establishment_id', $establishment->id)
            ->where('status', DemandAlert::STATUS_ACTIVE)
            ->latest('started_at')
            ->get();

        $alertHistory = DemandAlert::query()
            ->where('establishment_id', $establishment->id)
            ->where('status', DemandAlert::STATUS_RESOLVED)
            ->latest('started_at')
            ->limit(20)
            ->get();

        return view('manager.demand', [
            'establishment' => $establishment,
            'events' => $events,
            'detectorConfigured' => $service->detectorConfigured(),
            'activeAlerts' => $activeAlerts,
            'alertHistory' => $alertHistory,
        ]);
    }

    public function decide(
        PricingDecisionRequest $request,
        DemandPeak $peak,
        DemandPricingService $service,
        TotemTariffService $totems
    ): RedirectResponse {
        $data = $request->validated();

        $service->decide(
            $request->user(),
            $peak,
            $data['decision'] === 'approve',
            (int) $data['version']
        );

        $totems->syncEstablishment(
            $request->user()->establishment()->firstOrFail()
        );

        return back()->with(
            'success',
            $data['decision'] === 'approve'
                ? 'Reajuste aprovado. Confira a tarifa vigente para novas operações.'
                : 'A tarifa-base foi mantida para este pico.'
        );
    }
}
