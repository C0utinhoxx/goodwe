<?php

use App\Http\Middleware\IsGestor;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
