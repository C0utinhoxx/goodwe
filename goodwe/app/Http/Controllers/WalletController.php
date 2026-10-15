<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckoutRequest;
use App\Services\WalletService;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $history = $user->transactions()->where('is_demo', false)->latest()->take(7)->get();

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

    public function storeDeposit(StoreCheckoutRequest $request)
    {
        $validated = $request->validated();

        $amount = round((float) $validated['amount'], 2);

        $this->walletService->deposit(auth()->user(), $amount, $request->validated('idempotency_key'));

        return redirect()->route('wallet.index')
            ->with('success', __('wallet.deposit_success', ['amount' => number_format($amount, 2, ',', '.')]));
    }

    public function history(): View
    {
        $user = auth()->user();
        $history = $user->transactions()->where('is_demo', false)->latest()->paginate(15, ['*'], 'transactions_page');
        $sessions = $user->chargingSessions()->where('is_demo', false)->with(['establishment', 'coupon', 'reservation'])->latest()->paginate(15, ['*'], 'sessions_page');

        return view('user.carteira-historico', [
            'user' => $user,
            'history' => $history,
            'sessions' => $sessions,
        ]);
    }
}
