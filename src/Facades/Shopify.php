<?php

namespace Decoupled\Shopify\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static \Decoupled\Shopify\Authentication\OAuthService oauth() */
/** @method static \Decoupled\Shopify\GraphQL\GraphqlClient graph() */
/** @method static \Decoupled\Shopify\Webhooks\WebhookManager webhooks() */
/** @method static \Decoupled\Shopify\Contracts\HttpClient client() */
class Shopify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shopify';
    }
}
