<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\ReserveChargerRequest;
use App\Models\Charger;
use App\Models\ChargingReservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function store(ReserveChargerRequest $request, Charger $charger, ReservationService $service): RedirectResponse|JsonResponse
    {
        abort_if($charger->is_demo, 404);

        try {
            $reservation = $service->reserve($request->user(), $charger, $request->validated('idempotency_key'));
        } catch (InsufficientBalanceException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 422)
                : back()->with('error', $exception->getMessage());
        } catch (ValidationException $exception) {
            // No fluxo web o motivo volta como flash para a própria tela de
            // onde veio o clique (ex.: tela de pagar recarga) exibir a razão.
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->with('error', (string) collect($exception->errors())->flatten()->first());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('chargers.index')->with('success', $this->confirmationMessage($reservation));
        }

        return response()->json(['reservation' => $reservation, 'server_time' => now()->toIso8601String()], 201);
    }

    private function confirmationMessage(ChargingReservation $reservation): string
    {
        if ($reservation->status !== 'active') {
            return 'Esta solicitação já foi processada; consulte o estado da reserva.';
        }

        $fee = (float) $reservation->reservation_fee;

        if ($fee > 0) {
            return 'Reserva confirmada! Taxa de R$ '.number_format($fee, 2, ',', '.').' cobrada do saldo (não reembolsável). Compareça antes do prazo informado.';
        }

        return 'Reserva confirmada! Compareça antes do prazo informado.';
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
