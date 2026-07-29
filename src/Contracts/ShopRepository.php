<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Support\Shop;

interface ShopRepository
{
    public function find(string $domain): ?Shop;
}
