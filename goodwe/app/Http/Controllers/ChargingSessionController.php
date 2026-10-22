<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\PayChargingSessionRequest;
use App\Models\ChargingSession;
use App\Services\ReservationFeeCalculator;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function show(Request $request, ChargingSession $session, ReservationFeeCalculator $fees): View
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_if($session->is_demo, 404);
        // Além do estabelecimento e do cupom, a tela carrega o carregador
        // da sessão: ele alimenta o bloco de reserva da próxima recarga, já
        // com a taxa do dia calculada. Sessões legadas sem carregador
        // cadastrado recebem `null` e mostram a alternativa de escolher um ponto.
        $session->load(['establishment', 'coupon', 'charger']);
        $reserveCharger = $session->charger !== null && ! $session->charger->is_demo ? $session->charger : null;

        return view('user/recarga-pagamento', [
            'session' => $session,
            'user' => $request->user(),
            'coupons' => $request->user()->coupons()->where('is_demo', false)->where('status', 'available')->latest()->get(),
            'reserveCharger' => $reserveCharger,
            'reserveFee' => $reserveCharger !== null ? $fees->quote($reserveCharger) : null,
        ]);
    }

    public function pay(PayChargingSessionRequest $request, ChargingSession $session, WalletService $wallet): RedirectResponse|JsonResponse
    {
        abort_if($session->is_demo, 404);
        try {
            $paid = $wallet->paySession($request->user(), $session, $request->validated('coupon_id'), $request->validated('idempotency_key'));
        } catch (InsufficientBalanceException $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422) : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson() ? response()->json(['session' => $paid]) : redirect()->route('charging-sessions.show', $paid)->with('success', 'Pagamento confirmado. Sua recarga já está no histórico.');
    }
}
