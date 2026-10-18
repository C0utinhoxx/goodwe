<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /**
     * Redireciona a raiz para o portal do usuário autenticado (ou login).
     *
     * Implementado como controller (e não closure) para permitir o
     * `route:cache` em produção — rotas com Closure não são serializáveis.
     */
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->route(auth()->user()->portalRoute())
            : redirect()->route('user.login');
    }
}
