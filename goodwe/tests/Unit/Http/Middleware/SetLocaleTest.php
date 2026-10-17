<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeRequest(array $query = [], ?string $acceptLanguage = null): Request
    {
        $server = $acceptLanguage === null
            ? []
            : ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage];

        return Request::create('/dashboard', 'GET', $query, [], [], $server);
    }

    /**
     * Injeta um spy como store de sessão. O helper session() resolve
     * app('session') diretamente; 'session.store' é pré-bindado porque o
     * SessionServiceProvider o constrói chamando app('session')->driver().
     */
    private function fakeSession(?string $storedLocale): Session&MockInterface
    {
        $session = Mockery::mock(Session::class);
        $session->shouldReceive('get')->with('locale', null)->andReturn($storedLocale);
        $session->shouldReceive('driver')->andReturnSelf();

        $this->app->instance('session', $session);
        $this->app->instance('session.store', $session);

        return $session;
    }

    private function runMiddleware(Request $request): void
    {
        (new SetLocale)->handle($request, fn () => new Response('ok'));
    }

    public function test_query_parameter_takes_precedence_and_is_persisted(): void
    {
        $session = $this->fakeSession('en');
        $session->shouldReceive('put')->once()->with(['locale' => 'es']);

        $this->runMiddleware($this->makeRequest(['locale' => 'es']));

        $this->assertSame('es', App::getLocale());
    }

    public function test_falls_back_to_session_locale_when_no_query_parameter(): void
    {
        $this->fakeSession('pt');

        $this->runMiddleware($this->makeRequest());

        $this->assertSame('pt', App::getLocale());
    }

    public function test_falls_back_to_preferred_language_without_session_value(): void
    {
        $session = $this->fakeSession(null);
        $session->shouldReceive('put')->once()->with(['locale' => 'pt']);

        $request = $this->makeRequest([], 'pt-BR,pt;q=0.9');

        $this->runMiddleware($request);

        $this->assertSame('pt', App::getLocale());
    }

    public function test_ignores_unsupported_query_locale(): void
    {
        $session = $this->fakeSession('en');
        $session->shouldReceive('put')->never();

        $this->runMiddleware($this->makeRequest(['locale' => 'fr']));

        $this->assertSame('en', App::getLocale());
    }

    #[DataProvider('alreadyPersistedLocales')]
    public function test_does_not_persist_when_locale_is_unchanged(string $storedLocale): void
    {
        $session = $this->fakeSession($storedLocale);
        $session->shouldReceive('put')->never();

        $request = $this->makeRequest([], 'es-ES,es;q=0.9');

        $this->runMiddleware($request);

        // A sessão tem prioridade sobre o Accept-Language, então o idioma
        // resolvido é o já persistido — e a sessão não é marcada como suja.
        $this->assertSame($storedLocale, App::getLocale());
    }

    public static function alreadyPersistedLocales(): array
    {
        return [
            'idioma igual ao preferido da requisição' => ['es'],
            'idioma diferente do preferido da requisição' => ['pt'],
        ];
    }
}
