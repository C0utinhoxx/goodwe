<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Busca uma página a mais para sinalizar ao app se ainda há histórico,
        // sem transferir a carteira inteira em uma única resposta.
        $perPage = min(max($request->integer('per_page', 50), 1), 200);
        $history = $this->walletService->getHistory($user, $perPage + 1);
        $hasMore = $history->count() > $perPage;

        return response()->json([
            'balance' => (float) $user->balance,
            'points' => (float) $user->points,
            'history' => $history->take($perPage)->values()->map(fn (Transaction $t) => $this->transformTransaction($t)),
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessions = $this->walletService->getRecentSessions($user);

        return response()->json([
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'charger_name' => $s->charger_name,
                'energy_kwh' => (float) $s->energy_kwh,
                'amount_charged' => (float) $s->amount_charged,
                'status' => $s->status,
                'payment_status' => $s->payment_status,
                'tariff_per_kwh' => $s->tariff_per_kwh,
                'measured_energy_kwh' => $s->measured_energy_kwh,
                'original_amount' => $s->original_amount,
                'discount_amount' => $s->discount_amount,
                'coupon_id' => $s->coupon_id,
                'reservation_id' => $s->charging_reservation_id,
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function deposit(StoreCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $amount = round((float) $validated['amount'], 2);
        $user = $request->user();

        $this->walletService->deposit($user, $amount);

        return response()->json([
            'message' => __('wallet.deposit_success', ['amount' => number_format($amount, 2, ',', '.')]),
            'balance' => (float) $user->refresh()->balance,
            'points' => (float) $user->points,
        ]);
    }

    private function transformTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
