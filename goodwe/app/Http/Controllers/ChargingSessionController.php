<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Requests\PayChargingSessionRequest;
use App\Models\ChargingSession;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function show(Request $request, ChargingSession $session): View
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_if($session->is_demo, 404);
        // A view só usa o estabelecimento e o cupom; carregar `charger` e
        // `reservation` custava duas consultas por abertura da tela.
        $session->load(['establishment', 'coupon']);

        return view('user/recarga-pagamento', ['session' => $session, 'user' => $request->user(), 'coupons' => $request->user()->coupons()->where('is_demo', false)->where('status', 'available')->latest()->get()]);
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
