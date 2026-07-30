<?php

namespace Amadulhaque\Shopify\Authentication;

use DateTimeInterface;
use Amadulhaque\Shopify\Contracts\OAuthStateRepository;
use Amadulhaque\Shopify\Support\Shop;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

class CacheOAuthStateRepository implements OAuthStateRepository
{
    public function __construct(private readonly CacheFactory $cache) {}

    public function put(string $state, Shop $shop, DateTimeInterface $expiresAt, array $data = []): void
    {
        $this->store()->put($this->key($state), new OAuthState($shop, $data), $expiresAt);
    }

    public function pull(string $state): ?OAuthState
    {
        $value = $this->store()->pull($this->key($state));

        return $value instanceof OAuthState ? $value : null;
    }

    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return $this->cache->store(config('shopify.state_cache_store') ?: null);
    }

    private function key(string $state): string
    {
        return config('shopify.state_cache_prefix', 'shopify:oauth:state:').hash('sha256', $state);
    }
}
