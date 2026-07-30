<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Support\Shop;

interface ShopRepository
{
    public function find(string $domain): ?Shop;
}
