<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientPointsException;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $history = $this->walletService->getHistory($user);

        return view('user.carteira', [
            'user' => $user,
            'history' => $history,
        ]);
    }

    public function depositForm(): View
    {
        return view('user.carteira-deposito', [
            'user' => auth()->user(),
        ]);
    }

    public function storeDeposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $amount = round((float) $validated['amount'], 2);

        $this->walletService->deposit(auth()->user(), $amount);

        return redirect()->route('wallet.index')
            ->with('success', __('wallet.deposit_success', ['amount' => number_format($amount, 2, ',', '.')]));
    }

    public function history(): View
    {
        $user = auth()->user();
        $history = $this->walletService->getHistory($user);
        $sessions = $this->walletService->getRecentSessions($user);

        return view('user.carteira-historico', [
            'user' => $user,
            'history' => $history,
            'sessions' => $sessions,
        ]);
    }

    public function redeemPoints(Request $request)
    {
        $validated = $request->validate([
            'points' => ['required', 'integer', 'min:100'],
        ]);

        $user = auth()->user();

        if ($user->points < $validated['points']) {
            return back()->with('error', __('wallet.insufficient_points'));
        }

        try {
            $this->walletService->redeemPoints($user, $validated['points']);

            return redirect()->route('wallet.index')
                ->with('success', __('wallet.redeem_success', ['points' => $validated['points']]));
        } catch (InsufficientPointsException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
