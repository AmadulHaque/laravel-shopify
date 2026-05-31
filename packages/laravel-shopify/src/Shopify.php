<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify;

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;

/**
 * The public entry point behind the `Shopify` facade.
 *
 * Provides a fluent surface over the API client, store lookups, the webhook
 * registry and billing.
 */
final readonly class Shopify
{
    public function __construct(
        private ClientFactory $clients,
        private StoreRepository $stores,
        private WebhookRegistry $webhooks,
        private BillingManager $billing,
        private CurrentStore $currentStore,
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

    /**
     * The webhook topic → handler registry (register topics at runtime).
     */
    public function webhooks(): WebhookRegistry
    {
        return $this->webhooks;
    }

    /**
     * The billing manager: subscribe, enforce plans, record usage.
     */
    public function billing(): BillingManager
    {
        return $this->billing;
    }

    /**
     * The store the current request acts on (used by billing enforcement).
     */
    public function currentStore(): ?ShopModel
    {
        return $this->currentStore->get();
    }

    /**
     * Override how the current store is resolved from the request.
     *
     * @param  Closure(Request): ?ShopModel  $callback
     */
    public function resolveStoreUsing(Closure $callback): void
    {
        $this->currentStore->using($callback);
    }
}
