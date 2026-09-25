<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\ChargingPoint;
use App\Services\ChargingSimulator;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargerController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function index(): JsonResponse
    {
        $chargers = array_map(
            fn (ChargingPoint $charger) => $this->transform($charger),
            ChargingSimulator::all(),
        );

        return response()->json([
            'chargers' => $chargers,
        ]);
    }

    public function schedule(Request $request, string $charger): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_plate' => ['required', 'string', 'max:8'],
            'duration_minutes' => ['required', 'integer', 'in:15,30,45,60,120'],
        ]);

        $scheduled = ChargingSimulator::occupy(
            $charger,
            $validated['vehicle_plate'],
            $validated['duration_minutes'],
        );

        if (! $scheduled) {
            return response()->json([
                'message' => __('chargers.not_available'),
            ], 422);
        }

        $user = $request->user();
        $amount = $validated['duration_minutes'] * 0.65;
        $energyKwh = $validated['duration_minutes'] * 0.37;

        try {
            $session = $this->walletService->payCharging($user, $amount, $charger, $energyKwh);
        } catch (InsufficientBalanceException $e) {
            ChargingSimulator::release($charger);

            return response()->json([
                'message' => __('wallet.insufficient_balance'),
            ], 422);
        }

        return response()->json([
            'message' => __('chargers.scheduled'),
            'charger' => $this->transform(ChargingSimulator::find($charger)),
            'session' => [
                'id' => $session->id,
                'charger_name' => $session->charger_name,
                'energy_kwh' => (float) $session->energy_kwh,
                'amount_charged' => (float) $session->amount_charged,
                'status' => $session->status,
            ],
            'user' => [
                'balance' => (float) $user->refresh()->balance,
                'points' => (int) $user->points,
            ],
        ], 201);
    }

    public function start(Request $request, string $charger): JsonResponse
    {
        $validated = $request->validate([
            'energy_kwh' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'charge_type' => ['required', 'string', 'in:full,partial'],
            'target_percent' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ]);

        $chargingPoint = ChargingSimulator::find($charger);

        if ($chargingPoint === null || ! $chargingPoint->isAvailable()) {
            return response()->json([
                'message' => __('chargers.not_available'),
            ], 422);
        }

        $user = $request->user();

        $estimatedMinutes = max(5, (int) ceil(($validated['energy_kwh'] / max(1, $chargingPoint->powerKw)) * 60));
        $vehiclePlate = 'APP-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
        ChargingSimulator::occupy($charger, $vehiclePlate, $estimatedMinutes);

        try {
            $session = $this->walletService->payCharging(
                $user,
                round($validated['amount'], 2),
                $chargingPoint->name,
                round($validated['energy_kwh'], 2),
            );
        } catch (InsufficientBalanceException $e) {
            ChargingSimulator::release($charger);

            return response()->json([
                'message' => __('wallet.insufficient_balance'),
            ], 422);
        }

        return response()->json([
            'message' => __('chargers.scheduled'),
            'charger' => $this->transform(ChargingSimulator::find($charger)),
            'session' => [
                'id' => $session->id,
                'charger_name' => $session->charger_name,
                'energy_kwh' => (float) $session->energy_kwh,
                'amount_charged' => (float) $session->amount_charged,
                'status' => $session->status,
            ],
            'user' => [
                'balance' => (float) $user->refresh()->balance,
                'points' => (int) $user->points,
            ],
        ], 201);
    }

    public function release(Request $request, string $charger): JsonResponse
    {
        $released = ChargingSimulator::release($charger);

        if (! $released) {
            return response()->json([
                'message' => __('chargers.not_available'),
            ], 404);
        }

        return response()->json([
            'message' => __('chargers.released'),
            'charger' => $this->transform(ChargingSimulator::find($charger)),
        ]);
    }

    private function transform(ChargingPoint $charger): array
    {
        return [
            'id' => $charger->id,
            'name' => $charger->name,
            'location' => $charger->location,
            'power_kw' => $charger->powerKw,
            'connector' => $charger->connector,
            'status' => $charger->status(),
            'is_available' => $charger->isAvailable(),
            'vehicle_plate' => $charger->vehiclePlate,
            'available_in_minutes' => $charger->availableInMinutes(),
            'estimated_price_per_hour' => round($charger->powerKw * 0.9, 2),
            'lat' => $charger->lat,
            'lng' => $charger->lng,
        ];
    }
}
