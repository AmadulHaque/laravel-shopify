<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Support\Shop;
use Illuminate\Http\Request;

interface ShopResolver
{
    public function resolve(Request $request): ?Shop;
}
