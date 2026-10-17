<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        if ($request->user()->vehicles()->count() >= 10) {
            return back()->with('error', 'Limite de 10 veículos por conta.');
        }

        $request->user()->vehicles()->create($request->validated());

        return back()->with('success', 'Veículo cadastrado com sucesso.');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $request->user()->id, 403);
        $vehicle->delete();

        return back()->with('success', 'Veículo removido.');
    }
}
