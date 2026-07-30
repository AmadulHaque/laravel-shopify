<?php

namespace Amadulhaque\Shopify;

use Amadulhaque\Shopify\Authentication\OAuthService;
use Amadulhaque\Shopify\Contracts\HttpClient;
use Amadulhaque\Shopify\GraphQL\GraphqlClient;
use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;
use Amadulhaque\Shopify\Webhooks\WebhookManager;
use Illuminate\Contracts\Container\Container;

class ShopifyManager
{
    public function __construct(private readonly Container $container) {}

    public function oauth(string|Shop|null $shop = null, string|AccessToken|null $accessToken = null): OAuthService
    {
        $oauth = $this->container->make(OAuthService::class);

        return $shop === null ? $oauth : $oauth->for($shop, $accessToken);
    }

    public function graph(string|Shop|null $shop = null, string|AccessToken|null $accessToken = null): GraphqlClient
    {
        $graph = $this->container->make(GraphqlClient::class);

        return $shop === null ? $graph : $graph->shop($shop, $accessToken);
    }

    public function webhooks(): WebhookManager
    {
        return $this->container->make(WebhookManager::class);
    }

    public function client(): HttpClient
    {
        return $this->container->make(HttpClient::class);
    }
}
