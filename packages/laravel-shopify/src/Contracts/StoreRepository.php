<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\DTOs\AccessToken;

/**
 * Persistence boundary for Shopify stores.
 *
 * Decouples the OAuth/lifecycle flow from the concrete Eloquent model so the
 * storage mechanism can be replaced without touching business logic.
 */
interface StoreRepository
{
    public function findByDomain(string $domain): ?ShopModel;

    /**
     * Create or update the store for the given domain from a fresh OAuth grant.
     */
    public function upsertFromOAuth(string $domain, AccessToken $token): ShopModel;

    /**
     * Flag the store as uninstalled (soft-deactivation) without deleting data.
     */
    public function markUninstalled(string $domain): void;
}
