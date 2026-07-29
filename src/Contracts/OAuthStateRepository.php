<?php

namespace Decoupled\Shopify\Contracts;

use DateTimeInterface;
use Decoupled\Shopify\Support\Shop;

interface OAuthStateRepository
{
    public function put(string $state, Shop $shop, DateTimeInterface $expiresAt): void;

    /** Returns and consumes a state exactly once. */
    public function pull(string $state): ?Shop;
}
