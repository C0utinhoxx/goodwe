<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserRequest;
use App\Http\Requests\RedefinirRequest;
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

    public function forgotPassword()
    {
        return view('user.senha');
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

            return redirect()->route('chargers.index')->with('success', __('auth.logged_in'));
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ]);
    }

    public function update_password(RedefinirRequest $request)
    {
        $request->validated();

        $user = User::where('email', $request->email)->first();
        if(!$user){
            return back()->withErrors([
                'email' => __('auth.user_not_found'),
            ]);
        }

        $user->update([
            'password' => bcrypt($request->password),
        ]);
        return redirect()->route('user.login')->with('success', __('auth.password_updated'));

    }

    public function logout()
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('user.login');
    }
}
