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
        $data = ['user' => $user, 'earned' => $user->pointMovements()->where('type', 'deposit')->sum('amount'), 'spent' => -$user->pointMovements()->where('type', 'redemption')->sum('amount'), 'catalog' => config('loyalty.coupons'), 'coupons' => $user->coupons()->latest()->paginate(9, ['*'], 'coupons_page'), 'movements' => $user->pointMovements()->latest('id')->paginate(15, ['*'], 'points_page')];

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
