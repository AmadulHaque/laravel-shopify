<?php

namespace Decoupled\Shopify\Authentication;

use Decoupled\Shopify\Support\AccessToken;
use Decoupled\Shopify\Support\Shop;

final readonly class OAuthResult
{
    public function __construct(
        public Shop $shop,
        public AccessToken $token,
        /** Application state supplied when OAuth was started. */
        public array $state = [],
    ) {}
}
