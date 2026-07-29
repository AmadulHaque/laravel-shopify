<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Support\Shop;
use Illuminate\Http\Request;

interface ShopResolver
{
    public function resolve(Request $request): ?Shop;
}
