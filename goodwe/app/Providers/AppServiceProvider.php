<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\MercadoPagoGateway;
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
    }
}
