<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReserveChargerRequest;
use App\Models\Charger;
use App\Models\ChargingReservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(ReserveChargerRequest $request, Charger $charger, ReservationService $service): RedirectResponse|JsonResponse
    {
        abort_if($charger->is_demo, 404);
        $reservation = $service->reserve($request->user(), $charger, $request->validated('idempotency_key'));

        return $request->expectsJson() ? response()->json(['reservation' => $reservation, 'server_time' => now()->toIso8601String()], 201) : redirect()->route('chargers.index')->with('success', $reservation->status === 'active' ? 'Reserva confirmada! Compareça antes do prazo informado.' : 'Esta solicitação já foi processada; consulte o estado da reserva.');
    }

    public function cancel(Request $request, ChargingReservation $reservation, ReservationService $service): RedirectResponse|JsonResponse
    {
        abort_if($reservation->is_demo, 404);
        $cancelled = $service->cancel($request->user(), $reservation);

        return $request->expectsJson() ? response()->json(['reservation' => $cancelled]) : redirect()->route('chargers.index')->with('success', 'Reserva cancelada gratuitamente. Nenhum ponto foi descontado.');
    }

    public function status(Request $request, ChargingReservation $reservation, ReservationService $service): JsonResponse
    {
        abort_unless($request->user()->id === $reservation->user_id, 403);
        abort_if($reservation->is_demo, 404);
        $service->expire($reservation);

        return response()->json(['reservation' => $reservation->refresh(), 'server_time' => now()->toIso8601String()]);
    }
}
