<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\DTOs\GraphQlResponse;
use AmadulHaque\LaravelShopify\DTOs\RestResponse;

/**
 * A rate-limit-aware Shopify Admin API client, scoped to a single store.
 *
 * Implementations are immutable per store: {@see self::forStore()} returns a
 * clone bound to the given store, preventing cross-tenant token bleed when the
 * underlying client is resolved as a singleton.
 */
interface ShopifyApiClient
{
    /**
     * Return a client instance bound to the given store's credentials.
     */
    public function forStore(ShopModel $store): static;

    /**
     * Execute a GraphQL query or mutation against the Admin API.
     *
     * @param  array<string, mixed>  $variables
     */
    public function graphql(string $query, array $variables = []): GraphQlResponse;

    /**
     * Execute a REST Admin API request (fallback for endpoints without GraphQL parity).
     *
     * @param  array<string, mixed>  $payload
     */
    public function rest(string $method, string $endpoint, array $payload = []): RestResponse;
}
