<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\Shopify;
use Illuminate\Http\Request;

/**
 * Resolves the "current" store for an inbound request.
 *
 * Used by the billing-enforcement middleware. The default implementation reads
 * the `shop` route/query parameter; host apps can override resolution via
 * {@see Shopify::resolveStoreUsing()}.
 */
interface StoreResolver
{
    public function resolve(Request $request): ?ShopModel;
}
