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

        config()->set('manager.dashboard_cache_seconds', 0);

        $this->app->instance(
            FirestoreService::class,
            new FakeFirestoreService
        );

        $this->withSession([
            'locale' => 'pt',
        ]);

        app()->setLocale('pt');
    }
}
