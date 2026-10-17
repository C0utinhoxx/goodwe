<?php

use App\Http\Middleware\HasEstablishment;
use App\Http\Middleware\IsGestor;
use App\Http\Middleware\IsPortalUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'portal.user' => IsPortalUser::class,
            'establishment' => HasEstablishment::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route($request->is('gestor/*') ? 'manager.login' : 'user.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->portalRoute()));
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'webhook/mercadopago',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

