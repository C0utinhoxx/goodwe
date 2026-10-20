<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O cache do dashboard ficaria obsoleto dentro de um mesmo teste ( criar
        // sessões e reler o painel); os testes de cache ativam o TTL sob demanda.
        config()->set('manager.dashboard_cache_seconds', 0);
    }
}
