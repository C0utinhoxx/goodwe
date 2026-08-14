<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Livro;


class UserController extends Controller
{

    public function index() {
        $user = Auth::user();  // pega o usuário autenticado
    }
    public function login()
    {
    return view('user.login');
    }   
    public function create()
    {
        // Carregar a VIEW
        return view('user.cadastro');
    }

    public function store(UserRequest $request)
    {
        // Validar o formulário
        $request->validated();

        // Cadastrar o usuário no BD
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Redirecionar o usuário, enviar a mensagem de sucesso
        return redirect()->route('user.login')->with('success', 'Usuário cadastrado com sucesso!');

    }

    // Processa a tentativa de login
   public function logar(LoginRequest $request)
{
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        return redirect()->route('chargers.index')->with('success', 'Usuário logado com sucesso!');
    }

    return back()->withErrors([
        'email' => 'Credenciais inválidas.',
    ]);
}
    
}