<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;

interface TokenRepository
{
    public function findFor(Shop $shop): ?AccessToken;
}
