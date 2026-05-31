<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify;

use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;

/**
 * The public entry point behind the `Shopify` facade.
 *
 * Provides a fluent surface over the API client and store lookups; later phases
 * extend it with ->webhooks() and ->billing().
 */
final readonly class Shopify
{
    public function __construct(
        private ClientFactory $clients,
        private StoreRepository $stores,
    ) {}

    /**
     * The Admin API client. Call ->forStore($store) before issuing requests.
     */
    public function api(): ShopifyApiClient
    {
        return $this->clients->default();
    }

    /**
     * A client already bound to the given store.
     */
    public function for(ShopModel $store): ShopifyApiClient
    {
        return $this->clients->make($store);
    }

    public function store(string $domain): ?ShopModel
    {
        return $this->stores->findByDomain($domain);
    }
}
