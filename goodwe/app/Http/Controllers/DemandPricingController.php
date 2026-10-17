<?php

namespace App\Http\Controllers;

use App\Http\Requests\PricingDecisionRequest;
use App\Models\DemandPeak;
use App\Services\DemandPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemandPricingController extends Controller
{
    public function index(Request $request, DemandPricingService $service): View
    {
        $establishment = $request->user()->establishment;
        $establishment->load('activePeak');

        return view('manager.demand', ['establishment' => $establishment, 'events' => $establishment->tariffEvents()->with('actor:id,name')->latest('id')->paginate(20), 'detectorConfigured' => $service->detectorConfigured()]);
    }

    public function decide(PricingDecisionRequest $request, DemandPeak $peak, DemandPricingService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->decide($request->user(), $peak, $data['decision'] === 'approve', (int) $data['version']);

        return back()->with('success', $data['decision'] === 'approve' ? 'Reajuste aprovado. Confira a tarifa vigente para novas operações.' : 'A tarifa-base foi mantida para este pico.');
    }
}
