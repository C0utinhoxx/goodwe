<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('locale') ?? session('locale');

        if ($locale && in_array($locale, ['pt', 'en', 'es'])) {
            App::setLocale($locale);
            session(['locale' => $locale]);
        } else {
            $locale = $request->getPreferredLanguage(['pt', 'en', 'es']);
            App::setLocale($locale);
            session(['locale' => $locale]);
        }

        return $next($request);
    }
}
