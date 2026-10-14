<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /** Idiomas suportados, em ordem de preferência. */
    private const SUPPORTED = ['pt', 'en', 'es'];

    public function handle(Request $request, Closure $next)
    {
        $current = session('locale');

        $locale = $this->resolveLocale($request, $current);

        App::setLocale($locale);

        // Persiste apenas na mudança. Como SESSION_DRIVER=database, gravar em
        // toda requisição significa um INSERT/UPDATE na tabela `sessions`
        // por request — desnecessário quando o idioma já é o correto.
        if ($locale !== $current) {
            session(['locale' => $locale]);
        }

        return $next($request);
    }

    /**
     * Resolve o idioma: parâmetro da query > sessão > Accept-Language.
     */
    private function resolveLocale(Request $request, ?string $current): string
    {
        if ($this->isSupported($request->query('locale'))) {
            return $request->query('locale');
        }

        if ($this->isSupported($current)) {
            return $current;
        }

        return $request->getPreferredLanguage(self::SUPPORTED);
    }

    private function isSupported(mixed $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }
}
