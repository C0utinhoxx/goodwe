<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ManagerAuthController extends Controller
{
    public function login(): View
    {
        return view('user.login', ['managerAccess' => true]);
    }

    public function create(): View
    {
        return view('user.cadastro', ['managerAccess' => true]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $manager = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
            'password' => $data['password'], 'is_admin' => true,
        ]);
        Auth::login($manager);
        $request->session()->regenerate();

        return redirect()->route('manager.establishment.setup')->with('success', __('auth.manager_registered'));
    }

    public function authenticate(LoginRequest $request): RedirectResponse
    {
        $credentials = [...$request->safe()->only(['email', 'password']), 'is_admin' => true, 'is_support' => false];
        if (! Auth::attempt($credentials, $request->boolean('lembrar'))) {
            return back()->withErrors(['email' => __('auth.manager_failed')])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->route($request->user()->portalRoute());
    }

    public function setup(Request $request): RedirectResponse
    {
        return redirect()->route('manager.establishment.setup');
    }
}
