<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordRecoveryRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UserRequest;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function login(): View
    {
        return view('user.login');
    }

    public function create(): View
    {
        return view('user.cadastro');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'is_admin' => false,
        ]);

        return redirect()->route('user.login')->with('success', __('auth.registered'));
    }

    public function logar(LoginRequest $request): RedirectResponse
    {
        $credentials = [...$request->safe()->only(['email', 'password']), 'is_admin' => false];

        if (Auth::attempt($credentials, $request->boolean('lembrar'))) {
            request()->session()->regenerate();

            return redirect()->route('user.home')->with('success', __('auth.logged_in'));
        }

        return back()->withErrors([
            'email' => __('auth.driver_failed'),
        ])->onlyInput('email');
    }

    public function showPasswordRecovery(): View
    {
        return view('user.recover');
    }

    public function verifyPasswordRecovery(PasswordRecoveryRequest $request): RedirectResponse
    {
        $request->validated();

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors([
                'email' => __('auth.user_not_found'),
            ]);
        }

        if ($user->phone === null || $user->phone !== $request->phone) {
            session()->forget([
                'password_recovery_user_id',
                'password_recovery_verified',
                'password_recovery_email',
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['phone' => __('auth.phone_mismatch')]);
        }

        session([
            'password_recovery_user_id' => $user->id,
            'password_recovery_verified' => true,
            'password_recovery_email' => $user->email,
        ]);

        return back()->with('success', __('auth.identity_verified'));
    }

    public function updatePassword(PasswordResetRequest $request): RedirectResponse
    {
        $userId = session('password_recovery_user_id');

        if ($userId === null) {
            return redirect()->route('user.password.recovery')
                ->withErrors(['email' => __('auth.verify_before_reset')]);
        }

        $user = User::findOrFail($userId);

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        session()->forget([
            'password_recovery_user_id',
            'password_recovery_verified',
            'password_recovery_email',
        ]);

        return redirect()->route($user->isGestor() ? 'manager.login' : 'user.login')->with('success', __('auth.password_updated'));
    }

    public function profile(): View
    {
        return view('user.profile', ['vehicles' => auth()->user()->vehicles()->latest()->get()]);
    }

    public function home(ReservationService $reservations): View
    {
        $user = auth()->user();
        $reservations->expireDue();
        $chargers = Charger::all();

        return view('user.home-usuario', [
            'sessions' => $user->chargingSessions()->with(['establishment', 'coupon'])->latest()->take(5)->get(),
            'pendingSession' => $user->chargingSessions()->where('status', ChargingSession::STATUS_PENDING)->latest()->first(),
            'activeReservations' => $user->reservations()->with(['charger', 'establishment'])->where('status', 'active')->latest()->get(),
            'recentTransactions' => $user->transactions()->latest()->take(5)->get(),
            'totalSessions' => $user->chargingSessions()->where('status', ChargingSession::STATUS_COMPLETED)->count(),
            'availableChargers' => collect($chargers)->filter(fn ($charger) => $charger->isAvailable())->count(),
            'totalChargers' => count($chargers),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->email !== $data['email']) {
            $request->user()->email_verified_at = null;
        }
        $request->user()->fill($data)->save();

        return back()->with('success', 'Dados atualizados com sucesso.');
    }

    public function logout(): RedirectResponse
    {
        $loginRoute = auth()->user()?->isGestor() ? 'manager.login' : 'user.login';
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route($loginRoute);
    }
}
