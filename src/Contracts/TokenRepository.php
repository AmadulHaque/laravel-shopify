<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Support\AccessToken;
use Decoupled\Shopify\Support\Shop;

interface TokenRepository
{
    public function findFor(Shop $shop): ?AccessToken;
}
