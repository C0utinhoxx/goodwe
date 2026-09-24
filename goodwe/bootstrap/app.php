<?php

use App\Http\Middleware\IsGestor;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->alias([
            'gestor' => IsGestor::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('user.login'));
        $middleware->redirectUsersTo(fn () => route('chargers.index'));
    })
    ->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhook/mercadopago',
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
