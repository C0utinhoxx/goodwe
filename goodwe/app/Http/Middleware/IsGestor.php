<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsGestor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isGestor()) {
            return redirect()->route('user.home')->with('error', 'Voce nao tem permissao para acessar esta pagina.');
        }

        return $next($request);
    }
}
