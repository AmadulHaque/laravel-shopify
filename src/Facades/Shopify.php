<?php

namespace Amadulhaque\Shopify\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static \Amadulhaque\Shopify\Authentication\OAuthService oauth(string|\Amadulhaque\Shopify\Support\Shop|null $shop = null, string|\Amadulhaque\Shopify\Support\AccessToken|null $accessToken = null) */
/** @method static \Amadulhaque\Shopify\GraphQL\GraphqlClient graph(string|\Amadulhaque\Shopify\Support\Shop|null $shop = null, string|\Amadulhaque\Shopify\Support\AccessToken|null $accessToken = null) */
/** @method static \Amadulhaque\Shopify\Webhooks\WebhookManager webhooks() */
/** @method static \Amadulhaque\Shopify\Contracts\HttpClient client() */
class Shopify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shopify';
    }
}
