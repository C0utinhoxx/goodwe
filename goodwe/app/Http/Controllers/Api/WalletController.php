<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientPointsException;
use App\Http\Controllers\Controller;
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
        $history = $this->walletService->getHistory($user);

        return response()->json([
            'balance' => (float) $user->balance,
            'points' => (int) $user->points,
            'history' => $history->map(fn (Transaction $t) => $this->transformTransaction($t)),
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
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $user = $request->user();

        $this->walletService->deposit($user, $amount);

        return response()->json([
            'message' => __('wallet.deposit_success', ['amount' => number_format($amount, 2, ',', '.')]),
            'balance' => (float) $user->refresh()->balance,
            'points' => (int) $user->points,
        ]);
    }

    public function redeemPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'min:100'],
        ]);

        $user = $request->user();

        if ($user->points < $validated['points']) {
            return response()->json([
                'message' => __('wallet.insufficient_points'),
            ], 422);
        }

        try {
            $this->walletService->redeemPoints($user, $validated['points']);

            return response()->json([
                'message' => __('wallet.redeem_success', ['points' => $validated['points']]),
                'balance' => (float) $user->refresh()->balance,
                'points' => (int) $user->points,
            ]);
        } catch (InsufficientPointsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
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
