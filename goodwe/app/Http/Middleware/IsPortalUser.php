<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && ! $request->user()->isGestor(), 403, __('auth.driver_only'));

        return $next($request);
    }
}
