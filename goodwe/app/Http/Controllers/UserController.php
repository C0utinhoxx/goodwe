<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordRecoveryRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
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

    public function store(UserRequest $request): RedirectResponse
    {
        $request->validated();

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
        ]);

        return redirect()
            ->route('user.login')
            ->with('success', __('auth.registered'));
    }

    /*
    |--------------------------------------------------------------------------
    | Login com redirecionamento por perfil
    |--------------------------------------------------------------------------
    */

    public function logar(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
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

        return redirect()
            ->route('user.login')
            ->with('success', __('auth.password_updated'));
    }

    /*
    |--------------------------------------------------------------------------
    | Perfil do usuário
    |--------------------------------------------------------------------------
    */

    public function profile(): View
    {
        return view('user.profile');
    }

    /*
    |--------------------------------------------------------------------------
    | Página inicial
    |--------------------------------------------------------------------------
    */

    public function home(): View|RedirectResponse
    {
        $user = Auth::user();

        // A conta de suporte permanece na Central de Atendimento.
        if ($user->isSuporte()) {
            return redirect()->route('support.dashboard');
        }

        // Usuários comuns e gestores podem acessar a página Início.
        return view('user.home-usuario');
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('user.login');
    }
}
