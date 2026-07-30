<?php

namespace Decoupled\Shopify\Contracts;

use DateTimeInterface;
use Decoupled\Shopify\Authentication\OAuthState;
use Decoupled\Shopify\Support\Shop;

interface OAuthStateRepository
{
    /** @param array<string, mixed> $data */
    public function put(string $state, Shop $shop, DateTimeInterface $expiresAt, array $data = []): void;

    /** Returns and consumes a state exactly once. */
    public function pull(string $state): ?OAuthState;
}
