<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

/**
 * Builds {@see ShopifyApiClient} instances.
 */
interface ClientFactory
{
    /**
     * Build a client already bound to the given store.
     */
    public function make(ShopModel $store): ShopifyApiClient;

    /**
     * Return an unbound client; callers must call forStore() before requesting.
     */
    public function default(): ShopifyApiClient;
}
