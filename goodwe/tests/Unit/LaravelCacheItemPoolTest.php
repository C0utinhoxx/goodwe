<?php

namespace Tests\Unit;

use App\Support\LaravelCacheItemPool;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;

class LaravelCacheItemPoolTest extends TestCase
{
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Repository(new ArrayStore);
    }

    public function test_token_saved_by_one_pool_is_reused_by_the_next_request(): void
    {
        $firstRequest = new LaravelCacheItemPool($this->cache, 'firebase-auth-');
        $item = $firstRequest->getItem('token');

        $this->assertFalse($item->isHit());

        $item->set(['access_token' => 'abc123', 'expires_at' => time() + 3000]);
        $item->expiresAfter(1500);

        $this->assertTrue($firstRequest->save($item));

        // Nova instância = próxima requisição HTTP.
        $nextRequest = new LaravelCacheItemPool($this->cache, 'firebase-auth-');
        $cached = $nextRequest->getItem('token');

        $this->assertTrue($cached->isHit());
        $this->assertSame('abc123', $cached->get()['access_token']);
    }

    public function test_expired_token_is_a_cache_miss(): void
    {
        $pool = new LaravelCacheItemPool($this->cache, 'firebase-auth-');
        $item = $pool->getItem('expired');
        $item->set(['access_token' => 'old']);
        $item->expiresAfter(-1);
        $pool->save($item);

        $this->assertFalse($pool->getItem('expired')->isHit());
    }

    public function test_deferred_items_are_saved_on_commit(): void
    {
        $pool = new LaravelCacheItemPool($this->cache, 'firebase-auth-');
        $item = $pool->getItem('deferred');
        $item->set(['access_token' => 'later']);
        $item->expiresAfter(60);

        $this->assertTrue($pool->saveDeferred($item));
        $this->assertFalse($pool->getItem('deferred')->isHit());
        $this->assertTrue($pool->commit());
        $this->assertTrue($pool->getItem('deferred')->isHit());
    }

    public function test_invalid_keys_are_rejected(): void
    {
        $pool = new LaravelCacheItemPool($this->cache, 'firebase-auth-');

        $this->expectException(\InvalidArgumentException::class);

        $pool->getItem('chave:invalida');
    }
}
