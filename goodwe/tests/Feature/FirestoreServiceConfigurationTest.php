<?php

namespace Tests\Feature;

use App\Services\FirestoreService;
use App\Support\LaravelCacheItemPool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class FirestoreServiceConfigurationTest extends TestCase
{
    public function test_auth_token_cache_is_passed_inside_the_credentials_config(): void
    {
        $options = $this->clientOptions();

        // O SDK lê o cache de token em `credentialsConfig`; no nível raiz a
        // opção é ignorada e cada requisição buscaria um novo token OAuth.
        $this->assertInstanceOf(
            LaravelCacheItemPool::class,
            $options['credentialsConfig']['authCache']
        );
        $this->assertSame(1500, $options['credentialsConfig']['authCacheOptions']['lifetime']);
        $this->assertArrayHasKey('credentials', $options);
    }

    public function test_auth_token_cache_can_be_disabled_by_configuration(): void
    {
        config()->set('firebase.auth_cache.enabled', false);

        $options = $this->clientOptions();

        $this->assertArrayNotHasKey('credentialsConfig', $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientOptions(): array
    {
        $service = (new ReflectionClass(FirestoreService::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty(FirestoreService::class, 'projectId'))
            ->setValue($service, 'powertag-test');

        return (new ReflectionMethod(FirestoreService::class, 'clientOptions'))
            ->invoke($service, storage_path('app/firebase/credentials.json'));
    }
}
