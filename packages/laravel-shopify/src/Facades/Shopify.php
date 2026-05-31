<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Facades;

use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ShopifyApiClient api()
 * @method static ShopifyApiClient for(ShopModel $store)
 * @method static ShopModel|null store(string $domain)
 *
 * @see \AmadulHaque\LaravelShopify\Shopify
 */
final class Shopify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shopify';
    }
}
