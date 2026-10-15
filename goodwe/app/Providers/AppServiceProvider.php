<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\MercadoPagoGateway;
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
        $this->app->bind(PaymentGatewayInterface::class, MercadoPagoGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($accessToken = config('mercadopago.access_token')) {
            MercadoPagoConfig::setAccessToken($accessToken);
        }

        // O Render serve a aplicação em HTTPS, mas o APP_URL configurado no
        // painel pode estar em http://. Sem forçar o esquema, url() e asset()
        // geram URLs http:// e o navegador bloqueia tudo como Mixed Content,
        // deixando a página sem CSS e sem JavaScript.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
