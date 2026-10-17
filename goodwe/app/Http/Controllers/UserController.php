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
use App\Services\FirestoreService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Tela de login
    |--------------------------------------------------------------------------
    */

    public function login(): View
    {
        return view('user.login');
    }

    /*
    |--------------------------------------------------------------------------
    | Tela de cadastro
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        return view('user.cadastro');
    }

    /*
    |--------------------------------------------------------------------------
    | Cadastro de usuário comum
    |--------------------------------------------------------------------------
    */

    public function store(UserRequest $request, FirestoreService $firestore): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'is_admin' => false,
        ]);

        $firestore->saveDocument('users', "WEB_USER_{$user->id}", [
            'saldo_pontos' => $user->points,
            'status_tag' => 'ativo',
            'comando_web' => 'espera',
        ]);

        return redirect()->route('user.login')->with('success', __('auth.registered'));
    }

    /*
    |--------------------------------------------------------------------------
    | Login com redirecionamento por perfil
    |--------------------------------------------------------------------------
    */

    public function logar(LoginRequest $request): RedirectResponse
    {
        $credentials = [...$request->safe()->only(['email', 'password']), 'is_admin' => false];

        if (! Auth::attempt($credentials, $request->boolean('lembrar'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Perfil Suporte
        if ($user->isSuporte()) {
            return redirect()
                ->route('support.dashboard')
                ->with('success', __('auth.logged_in'));
        }

        // Perfil Gestor
        if ($user->isGestor()) {
            return redirect()
                ->route('manager.dashboard')
                ->with('success', __('auth.logged_in'));
        }

        // Usuário comum
        return redirect()
            ->route('user.home')
            ->with('success', __('auth.logged_in'));
    }

    /*
    |--------------------------------------------------------------------------
    | Tela de recuperação de senha
    |--------------------------------------------------------------------------
    */

    public function showPasswordRecovery(): View
    {
        return view('user.recover');
    }

    /*
    |--------------------------------------------------------------------------
    | Verificar identidade para recuperação de senha
    |--------------------------------------------------------------------------
    */

    public function verifyPasswordRecovery(
        PasswordRecoveryRequest $request
    ): RedirectResponse {
        $request->validated();

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors([
                'email' => __('auth.user_not_found'),
            ]);
        }

        // Contas de suporte precisam de recuperação
        // por um procedimento administrativo seguro.
        if ($user->isSuporte()) {
            session()->forget([
                'password_recovery_user_id',
                'password_recovery_verified',
                'password_recovery_email',
            ]);

            return back()->withErrors([
                'email' => 'Entre em contato com a administração para recuperar o acesso.',
            ]);
        }

        if (
            $user->phone === null ||
            $user->phone !== $request->phone
        ) {
            session()->forget([
                'password_recovery_user_id',
                'password_recovery_verified',
                'password_recovery_email',
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'phone' => __('auth.phone_mismatch'),
                ]);
        }

        session([
            'password_recovery_user_id' => $user->id,
            'password_recovery_verified' => true,
            'password_recovery_email' => $user->email,
        ]);

        return back()->with(
            'success',
            __('auth.identity_verified')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Atualizar senha
    |--------------------------------------------------------------------------
    */

    public function updatePassword(
        PasswordResetRequest $request
    ): RedirectResponse {
        $request->validated();

        $userId = session('password_recovery_user_id');

        if (
            $userId === null ||
            session('password_recovery_verified') !== true
        ) {
            return redirect()
                ->route('user.password.recovery')
                ->withErrors([
                    'email' => __('auth.verify_before_reset'),
                ]);
        }

        $user = User::findOrFail($userId);

        if ($user->isSuporte()) {
            session()->forget([
                'password_recovery_user_id',
                'password_recovery_verified',
                'password_recovery_email',
            ]);

            return redirect()
                ->route('user.password.recovery')
                ->withErrors([
                    'email' => 'Recuperação da conta de suporte deve ser feita pela administração.',
                ]);
        }

        $user->update([
            'password' => $request->password,
        ]);

        session()->forget([
            'password_recovery_user_id',
            'password_recovery_verified',
            'password_recovery_email',
        ]);

        return redirect()->route($user->isGestor() ? 'manager.login' : 'user.login')->with('success', __('auth.password_updated'));
    }

    /*
    |--------------------------------------------------------------------------
    | Perfil do usuário
    |--------------------------------------------------------------------------
    */

    public function profile(): View
    {
        return view('user.profile', ['vehicles' => auth()->user()->vehicles()->latest()->get()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Página inicial
    |--------------------------------------------------------------------------
    */

    public function home(ReservationService $reservations): View|RedirectResponse
    {
        $user = Auth::user();

        // A conta de suporte permanece na Central de Atendimento.
        if ($user->isSuporte()) {
            return redirect()->route('support.dashboard');
        }

        $reservations->expireDue();
        $chargers = Charger::where('is_demo', false)->get();

        return view('user.home-usuario', [
            'sessions' => $user->chargingSessions()->where('is_demo', false)->with(['establishment', 'coupon'])->latest()->take(5)->get(),
            'pendingSession' => $user->chargingSessions()->where('is_demo', false)->where('status', ChargingSession::STATUS_PENDING)->latest()->first(),
            'activeReservations' => $user->reservations()->where('is_demo', false)->with(['charger', 'establishment'])->where('status', 'active')->latest()->get(),
            'recentTransactions' => $user->transactions()->where('is_demo', false)->latest()->take(5)->get(),
            'totalSessions' => $user->chargingSessions()->where('is_demo', false)->where('status', ChargingSession::STATUS_COMPLETED)->count(),
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

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(): RedirectResponse
    {
        $loginRoute = auth()->user()?->isGestor() ? 'manager.login' : 'user.login';
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route($loginRoute);
    }
}
