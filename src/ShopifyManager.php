<?php

namespace Decoupled\Shopify;

use Decoupled\Shopify\Authentication\OAuthService;
use Decoupled\Shopify\Contracts\HttpClient;
use Decoupled\Shopify\GraphQL\GraphqlClient;
use Decoupled\Shopify\Webhooks\WebhookManager;
use Illuminate\Contracts\Container\Container;

class ShopifyManager
{
    public function __construct(private readonly Container $container) {}

    public function oauth(): OAuthService
    {
        return $this->container->make(OAuthService::class);
    }

    public function graph(): GraphqlClient
    {
        return $this->container->make(GraphqlClient::class);
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
