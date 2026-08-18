<?php

namespace App\Http\Controllers;

use App\Services\ChargingSimulator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingController extends Controller
{
    public function index(): View
    {
        return view('chargers.index', [
            'chargers' => ChargingSimulator::all(),
        ]);
    }

    public function schedule(Request $request, string $code): mixed
    {
        $validated = $request->validate([
            'vehicle_plate' => ['required', 'string', 'max:8'],
            'duration_minutes' => ['required', 'integer', 'in:15,30,45,60,120'],
        ]);

        $scheduled = ChargingSimulator::occupy(
            $code,
            $validated['vehicle_plate'],
            $validated['duration_minutes'],
        );

        if (! $scheduled) {
            return back()->with('error', __('chargers.not_available'));
        }

        return redirect()->route('chargers.index')
            ->with('success', __('chargers.scheduled'));
    }

    public function release(string $code): mixed
    {
        ChargingSimulator::release($code);

        return back()->with('success', __('chargers.released'));
    }
}
