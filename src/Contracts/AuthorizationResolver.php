<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Support\Shop;
use Illuminate\Http\Request;

interface AuthorizationResolver
{
    public function canAccess(Request $request, Shop $shop): bool;
}
