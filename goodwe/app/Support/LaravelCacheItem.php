<?php

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Item PSR-6 usado pelo pool que compartilha o token do Google entre
 * requisições.
 *
 * O item guarda o valor localmente e expõe `value()` para o pool salvar
 * também os itens criados em um cache miss (o `get()` segue a especificação
 * PSR-6 e devolve `null` nesse caso).
 */
final class LaravelCacheItem implements CacheItemInterface
{
    private mixed $value;

    private bool $hit;

    private ?DateTimeInterface $expiresAt = null;

    public function __construct(
        private readonly string $key,
        mixed $value = null,
        bool $hit = false
    ) {
        $this->value = $value;
        $this->hit = $hit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration;

        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        $this->expiresAt = $time instanceof DateInterval
            ? (new DateTimeImmutable)->add($time)
            : new DateTimeImmutable("+{$time} seconds");

        return $this;
    }

    /**
     * Valor a ser persistido, mesmo quando o item veio de um cache miss.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    public function expiration(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }
}
