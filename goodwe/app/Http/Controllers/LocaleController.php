<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /** Idiomas suportados pela interface. */
    private const SUPPORTED = ['pt', 'en', 'es'];

    /**
     * Persiste o idioma escolhido na sessão e volta para a página anterior.
     *
     * Implementado como controller (e não closure) para permitir o
     * `route:cache` em produção.
     */
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 400);

        session(['locale' => $locale]);

        return redirect()->back();
    }
}
