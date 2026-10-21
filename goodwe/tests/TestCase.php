<?php

namespace Tests;

use App\Services\FirestoreService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeFirestoreService;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O cache do dashboard ficaria obsoleto dentro de um mesmo teste ( criar
        // sessões e reler o painel); os testes de cache ativam o TTL sob demanda.
        config()->set('manager.dashboard_cache_seconds', 0);

        // Nenhum teste deve abrir conexão com o Firebase: a sincronização de
        // tarifa com o Firestore usa o fake em memória por padrão. Testes que
        // precisam inspecionar/forçar falhas trocam a instância no seu setUp.
        $this->app->instance(FirestoreService::class, new FakeFirestoreService);
    }
}
