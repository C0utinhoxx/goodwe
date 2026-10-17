<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    private function bootProviderInEnvironment(string $environment): void
    {
        $this->app['env'] = $environment;

        // Simula o APP_URL em http://, que é o cenário do Render.
        $this->app['url']->setRequest(Request::create('http://goodwe-1lzr.onrender.com'));

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_forces_https_scheme_in_production(): void
    {
        $this->bootProviderInEnvironment('production');

        $this->assertStringStartsWith('https://', url('/dashboard'));
    }

    public function test_forces_https_scheme_on_asset_urls_in_production(): void
    {
        $this->bootProviderInEnvironment('production');

        // O @vite do Laravel emite os assets por asset(); se a URL sair em
        // http://, o navegador bloqueia o CSS/JS como Mixed Content.
        $this->assertStringStartsWith('https://', asset('build/assets/app.css'));
    }

    public function test_does_not_force_scheme_outside_production(): void
    {
        $this->bootProviderInEnvironment('local');

        $this->assertStringStartsWith('http://', url('/dashboard'));
        $this->assertStringStartsWith('http://', asset('build/assets/app.css'));
    }

    public function test_forced_scheme_survives_root_url_reset(): void
    {
        $this->bootProviderInEnvironment('production');

        // route() recalcula a URL a cada chamada e não deve voltar para http.
        $this->assertStringStartsWith('https://', URL::to('/'));
        $this->assertStringStartsWith('https://', URL::route('user.login'));
    }
}
