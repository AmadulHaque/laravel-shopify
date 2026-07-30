<?php

namespace Decoupled\Shopify\Authentication;

use Decoupled\Shopify\Support\Shop;

final readonly class OAuthState
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public Shop $shop,
        public array $data = [],
    ) {}
}
