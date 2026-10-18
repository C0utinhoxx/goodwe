<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SupportDashboardController extends Controller
{
    /**
     * Central de Atendimento — visão do perfil de suporte.
     *
     * Implementado como controller (e não closure) para permitir o
     * `route:cache` em produção.
     */
    public function __invoke(): View
    {
        return view('suporte.dashboard');
    }
}
