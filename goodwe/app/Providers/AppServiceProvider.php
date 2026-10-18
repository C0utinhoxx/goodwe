<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\User;
use App\Services\FirestoreService;
use App\Services\MercadoPagoGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use MercadoPago\MercadoPagoConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentGatewayInterface::class,
            MercadoPagoGateway::class
        );

        // O FirestoreService constrói um FirestoreClient (com autenticação)
        // no construtor. Como singleton, ele é criado uma única vez por
        // requisição e reutilizado em quem o injetar.
        $this->app->singleton(FirestoreService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configuração do Mercado Pago
        if ($accessToken = config('mercadopago.access_token')) {
            MercadoPagoConfig::setAccessToken($accessToken);
        }

        // Força HTTPS em produção (Render serve atrás de proxy)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Permissão para acessar a Central de Atendimento
        Gate::define('access-support', function (User $user) {
            return (bool) ($user->is_support ?? false);
        });
    }
}
