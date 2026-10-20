<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleChargingRequest;
use App\Http\Requests\StartChargingRequest;
use App\Models\Charger;
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

    public function index(Request $request): JsonResponse
    {
        $chargers = array_map(
            fn (ChargingPoint $charger) => $this->transform($charger),
            ChargingSimulator::all(),
        );

        // Limite por requisição: a rota pública podia devolver a base inteira
        // de carregadores (uma linha com tarifa/status por equipamento) a cada
        // chamada. Busca uma linha a mais para sinalizar se há mais páginas.
        $perPage = min(max($request->integer('per_page', 500), 1), 1000);
        $registered = Charger::where('is_demo', false)
            ->with('establishment')
            ->orderBy('name')
            ->limit($perPage + 1)
            ->get();
        $hasMoreRegistered = $registered->count() > $perPage;

        return response()->json([
            'chargers' => $chargers,
            'registered_chargers' => $registered->take($perPage)->map(fn (Charger $c) => ['id' => $c->code, 'name' => $c->name, 'establishment' => $c->establishment->name, 'location' => $c->location, 'power_kw' => (float) $c->power_kw, 'status' => $c->status(), 'tariff_per_kwh' => $c->currentTariff(), 'can_reserve' => $c->canReserve(), 'source' => 'registered']),
            'registered_chargers_per_page' => $perPage,
            'registered_chargers_has_more' => $hasMoreRegistered,
        ]);
    }

    public function schedule(ScheduleChargingRequest $request, string $charger): JsonResponse
    {
        if (Charger::where('code', $charger)->exists()) {
            return response()->json(['message' => 'A autorização presencial e a confirmação IoT deste equipamento ainda dependem da integração. Nenhuma recarga foi iniciada.', 'integration_pending' => true], 503);
        }
        try {
            $session = $this->payments->schedule($request->user(), $charger, $request->validated());
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->paymentResponse($request, $charger, $session);
    }

    public function start(StartChargingRequest $request, string $charger): JsonResponse
    {
        if (Charger::where('code', $charger)->exists()) {
            return response()->json(['message' => 'A autorização presencial e a confirmação IoT deste equipamento ainda dependem da integração. Nenhuma recarga foi iniciada.', 'integration_pending' => true], 503);
        }
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
        if (Charger::where('code', $charger)->exists()) {
            return response()->json(['message' => 'A parada e a disponibilidade física dependem de confirmação pelo IoT.', 'integration_pending' => true], 503);
        }
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
            'source' => 'simulation',
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
