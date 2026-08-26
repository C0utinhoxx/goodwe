<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Services\ChargingSimulator;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

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

        $user = auth()->user();
        $amount = $validated['duration_minutes'] * 0.65;
        $energyKwh = $validated['duration_minutes'] * 0.37;

        try {
            $this->walletService->payCharging($user, $amount, $code, $energyKwh);
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', __('wallet.insufficient_balance'));
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
