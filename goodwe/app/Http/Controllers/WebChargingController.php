<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\WebChargingRequest;
use App\Models\ChargingReservation;
use App\Models\ChargingSession;
use App\Services\WebChargingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WebChargingController extends Controller
{
    public function start(WebChargingRequest $request, ChargingReservation $reservation, WebChargingService $charging): RedirectResponse|JsonResponse
    {
        try {
            $session = $charging->start($request->user(), $reservation);
        } catch (InsufficientBalanceException $exception) {
            return $request->expectsJson() ? response()->json(['message' => $exception->getMessage()], 422) : back()->with('error', $exception->getMessage());
        }

        return $request->expectsJson() ? response()->json(['session' => $session], 201) : redirect()->route('charging-sessions.show', $session);
    }

    public function advance(WebChargingRequest $request, ChargingSession $session, WebChargingService $charging): JsonResponse
    {
        return response()->json(['session' => $charging->advance($request->user(), $session, $request->filled('speed') ? $request->integer('speed') : null)]);
    }

    public function end(WebChargingRequest $request, ChargingSession $session, WebChargingService $charging): RedirectResponse|JsonResponse
    {
        $ended = $charging->advance($request->user(), $session, end: true);

        return $request->expectsJson() ? response()->json(['session' => $ended]) : redirect()->route('charging-sessions.show', $ended)->with('success', 'Recarga encerrada. Confira o consumo e confirme o pagamento pela sua carteira.');
    }
}
