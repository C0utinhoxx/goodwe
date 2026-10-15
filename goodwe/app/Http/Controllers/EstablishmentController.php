<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstablishmentRequest;
use App\Services\DemandPricingService;
use App\Services\EstablishmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstablishmentController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $establishments = $request->user()->establishment()->limit(2)->get();
        abort_if($establishments->count() > 1, 409, __('auth.establishment_conflict'));
        if ($establishments->isNotEmpty()) {
            return redirect()->route('manager.establishment.edit');
        }

        return view('manager.establishment-form', ['establishment' => null]);
    }

    public function store(EstablishmentRequest $request, EstablishmentService $service): RedirectResponse
    {
        $service->create($request->user(), $request->safe()->except(['user_id', 'establishment_id', 'active_peak_id', 'peak_tariff']));

        return redirect()->route('manager.dashboard')->with('success', 'Estabelecimento cadastrado e vinculado à sua conta.');
    }

    public function edit(Request $request): View
    {
        return view('manager.establishment-form', ['establishment' => $request->user()->establishment]);
    }

    public function update(EstablishmentRequest $request, DemandPricingService $service): RedirectResponse
    {
        $service->updateSettings($request->user(), $request->user()->establishment, $request->safe()->except(['user_id', 'establishment_id', 'active_peak_id', 'peak_tariff']));

        return redirect()->route('manager.establishment.edit')->with('success', 'Dados e configurações atualizados. Reservas e sessões anteriores mantêm suas regras.');
    }
}
