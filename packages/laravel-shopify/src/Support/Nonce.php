<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

/**
 * Generates and verifies the OAuth `state` nonce used for CSRF protection.
 *
 * The nonce is stored server-side (cache by default) mapped to the shop it was
 * issued for, then atomically pulled on callback. Cache is used rather than the
 * session because the Shopify callback is a fresh top-level request with no
 * guaranteed shared session cookie.
 */
final readonly class Nonce
{
    public function __construct(
        private CacheRepository $cache,
        private string $prefix = 'shopify_oauth_nonce',
        private int $ttl = 600,
    ) {}

    public function generate(): string
    {
        return Str::random(40);
    }

    public function store(string $nonce, string $shop): void
    {
        $this->cache->put($this->key($nonce), $shop, $this->ttl);
    }

    /**
     * Atomically retrieve and remove the shop bound to the nonce.
     */
    public function pull(string $nonce): ?string
    {
        if ($nonce === '') {
            return null;
        }

        return $this->cache->pull($this->key($nonce));
    }

    private function key(string $nonce): string
    {
        return "{$this->prefix}:{$nonce}";
    }
}
