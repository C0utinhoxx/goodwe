<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleChargingRequest;
use App\Http\Requests\StartChargingRequest;
use App\Models\ChargingPoint;
use App\Models\ChargingSession;
use App\Services\ChargingPaymentService;
use App\Services\ChargingSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargerController extends Controller
{
    public function __construct(
        private ChargingPaymentService $payments,
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

    public function schedule(ScheduleChargingRequest $request, string $charger): JsonResponse
    {
        try {
            $session = $this->payments->schedule($request->user(), $charger, $request->validated());
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->paymentResponse($request, $charger, $session);
    }

    public function start(StartChargingRequest $request, string $charger): JsonResponse
    {
        try {
            $session = $this->payments->start($request->user(), $charger, $request->validated());
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->paymentResponse($request, $charger, $session);
    }

    private function paymentResponse(Request $request, string $charger, ChargingSession $session): JsonResponse
    {
        return response()->json(['message' => __('chargers.scheduled'), 'charger' => $this->transform(ChargingSimulator::find($charger)), 'session' => ['id' => $session->id, 'charger_name' => $session->charger_name, 'energy_kwh' => (float) $session->energy_kwh, 'original_amount' => (float) $session->original_amount, 'discount_amount' => (float) $session->discount_amount, 'amount_charged' => (float) $session->amount_charged, 'coupon_id' => $session->coupon_id, 'transaction_id' => $session->transaction_id, 'status' => $session->status], 'user' => ['balance' => (float) $request->user()->refresh()->balance, 'points' => (float) $request->user()->points]], 201);
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
