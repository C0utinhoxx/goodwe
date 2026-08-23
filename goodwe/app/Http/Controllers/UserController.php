<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function login()
    {
        return view('user.login');
    }

    public function create()
    {
        return view('user.cadastro');
    }

    public function store(UserRequest $request)
    {
        $request->validated();

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return redirect()->route('user.login')->with('success', __('auth.registered'));
    }

    public function logar(LoginRequest $request)
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

    public function profile()
    {
        return view('user.profile');
    }

    public function home()
    {
        return view('user.home-usuario');
    }

    public function logout()
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('user.login');
    }
}
