<?php

use Decoupled\Shopify\Contracts\AuthorizationResolver;
use Decoupled\Shopify\Contracts\OAuthStateRepository;
use Decoupled\Shopify\Contracts\ShopRepository;
use Decoupled\Shopify\Contracts\ShopResolver;
use Decoupled\Shopify\Contracts\TokenRepository;
use Decoupled\Shopify\Contracts\WebhookRepository;
use Decoupled\Shopify\Http\LaravelHttpClient;

return [
    'client_id' => env('SHOPIFY_CLIENT_ID'),
    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
    'redirect' => env('SHOPIFY_REDIRECT_URI'), // Alias retained for concise application configuration.
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'scopes' => array_filter(explode(',', (string) env('SHOPIFY_SCOPES', ''))),
    'http_client' => LaravelHttpClient::class,
    'http' => [
        'timeout' => (int) env('SHOPIFY_HTTP_TIMEOUT', 30),
        'retries' => (int) env('SHOPIFY_HTTP_RETRIES', 3),
        'retry_delay_ms' => (int) env('SHOPIFY_HTTP_RETRY_DELAY_MS', 250),
    ],
    'contracts' => [
        'state_repository' => OAuthStateRepository::class,
        'shop_repository' => ShopRepository::class,
        'token_repository' => TokenRepository::class,
        'shop_resolver' => ShopResolver::class,
        'webhook_repository' => WebhookRepository::class,
        'authorization_resolver' => AuthorizationResolver::class,
    ],
];
