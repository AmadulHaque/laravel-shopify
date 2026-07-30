<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Support\Shop;
use Illuminate\Http\Request;

interface AuthorizationResolver
{
    public function canAccess(Request $request, Shop $shop): bool;
}
