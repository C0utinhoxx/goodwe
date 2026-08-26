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
    public function login(): View
    {
        return view('user.login');
    }

    public function create(): View
    {
        return view('user.cadastro');
    }

    public function forgotPassword()
    {
        return view('user.senha');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $request->validated();

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        return redirect()->route('user.login')->with('success', __('auth.registered'));
    }

    public function logar(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            request()->session()->regenerate();

            return redirect()->route('user.home')->with('success', __('auth.logged_in'));
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ]);
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

        return redirect()->route('user.login')->with('success', __('auth.password_updated'));
    }

    public function profile(): View
    {
        return view('user.profile');
    }

    public function home(): View
    {
        return view('user.home-usuario');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('user.login');
    }
}
