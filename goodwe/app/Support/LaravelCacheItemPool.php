<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Adaptador PSR-6 sobre o cache do Laravel.
 *
 * O SDK do Google cria um pool em memória por requisição, o que faz cada
 * leitura/escrita no Firestore buscar um novo token OAuth (um round-trip
 * HTTPS extra). Usando este pool, o token é persistido no cache da aplicação
 * e reaproveitado por todas as requisições até expirar.
 *
 * Falhas do cache nunca interrompem o Firestore: nesse caso o item é tratado
 * como miss e o SDK segue o fluxo normal.
 */
final class LaravelCacheItemPool implements CacheItemPoolInterface
{
    /** @var array<string, CacheItemInterface> */
    private array $deferred = [];

    public function __construct(
        private readonly Repository $cache,
        private readonly string $prefix = ''
    ) {}

    public function getItem(string $key): CacheItemInterface
    {
        $this->assertValidKey($key);

        try {
            $value = $this->cache->get($this->prefix.$key);
        } catch (Throwable) {
            return new LaravelCacheItem($key);
        }

        return new LaravelCacheItem($key, $value, $value !== null);
    }

    /**
     * @param  string[]  $keys
     * @return array<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        $this->assertValidKey($key);

        try {
            return $this->cache->has($this->prefix.$key);
        } catch (Throwable) {
            return false;
        }
    }

    public function clear(): bool
    {
        // Limpar o cache inteiro apagaria dados não relacionados ao Firestore.
        return false;
    }

    public function deleteItem(string $key): bool
    {
        $this->assertValidKey($key);
        unset($this->deferred[$key]);

        try {
            return (bool) $this->cache->forget($this->prefix.$key);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  string[]  $keys
     */
    public function deleteItems(array $keys): bool
    {
        $deleted = true;

        foreach ($keys as $key) {
            $deleted = $this->deleteItem($key) && $deleted;
        }

        return $deleted;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (! $item instanceof LaravelCacheItem) {
            return false;
        }

        try {
            $key = $this->prefix.$item->getKey();
            $expiration = $item->expiration();

            return $expiration !== null
                ? (bool) $this->cache->put($key, $item->value(), $expiration)
                : (bool) $this->cache->forever($key, $item->value());
        } catch (Throwable) {
            return false;
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (! $item instanceof LaravelCacheItem) {
            return false;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $saved = true;

        foreach ($this->deferred as $item) {
            $saved = $this->save($item) && $saved;
        }

        $this->deferred = [];

        return $saved;
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || preg_match('/[{}()\/\\\\@:]/', $key) === 1) {
            throw new \InvalidArgumentException("Chave de cache inválida: {$key}");
        }
    }
}
