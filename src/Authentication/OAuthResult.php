<?php

namespace Amadulhaque\Shopify\Authentication;

use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;

final readonly class OAuthResult
{
    public function __construct(
        public Shop $shop,
        public AccessToken $token,
        /** Application state supplied when OAuth was started. */
        public array $state = [],
    ) {}
}
