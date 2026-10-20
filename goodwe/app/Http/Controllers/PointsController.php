<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientPointsException;
use App\Http\Requests\RedeemCouponRequest;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointsController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        // Os dois totais vêm de uma única agregação em vez de duas passadas
        // completas pela tabela de movimentações.
        $totals = $user->pointMovements()->where('is_demo', false)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as total_earned")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'redemption' THEN amount ELSE 0 END), 0) as total_spent")
            ->first();
        $data = ['user' => $user, 'earned' => (float) $totals->total_earned, 'spent' => -(float) $totals->total_spent, 'catalog' => config('loyalty.coupons'), 'coupons' => $user->coupons()->where('is_demo', false)->latest()->paginate(9, ['*'], 'coupons_page'), 'movements' => $user->pointMovements()->where('is_demo', false)->latest('id')->paginate(15, ['*'], 'points_page')];

        return $request->expectsJson() ? response()->json($data) : view('user.pontos', $data);
    }

    public function redeem(RedeemCouponRequest $request, LoyaltyService $loyalty): RedirectResponse|JsonResponse
    {
        try {
            $coupon = $loyalty->redeem($request->user(), $request->validated('tier'), $request->validated('idempotency_key'));
        } catch (InsufficientPointsException $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422) : back()->with('error', $e->getMessage());
        }

        return $request->expectsJson() ? response()->json(['coupon' => $coupon, 'points' => $request->user()->refresh()->points], 201) : redirect()->route('points.index')->with('success', "Cupom {$coupon->name} resgatado! Já está disponível para sua próxima recarga.");
    }
}
