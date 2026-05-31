<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Models\Store;
use AmadulHaque\LaravelShopify\Repositories\EloquentStoreRepository;

return [

    /*
    |--------------------------------------------------------------------------
    | App Credentials
    |--------------------------------------------------------------------------
    |
    | The API key and secret from your Shopify Partner dashboard, the scopes
    | your app requests, and the public URL Shopify redirects back to.
    |
    */

    'api_key' => env('SHOPIFY_API_KEY'),

    'api_secret' => env('SHOPIFY_API_SECRET'),

    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),

    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products'),

    'app_url' => env('SHOPIFY_APP_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Store Persistence
    |--------------------------------------------------------------------------
    |
    | The model and repository used to persist connected stores. Swap the model
    | for your own (implementing the ShopModel contract) to extend it.
    |
    */

    'store' => [
        'model' => Store::class,
        'repository' => EloquentStoreRepository::class,
        'table' => 'shopify_stores',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth
    |--------------------------------------------------------------------------
    |
    | token_type: "offline" (long-lived, per-shop) or "online" (per-user).
    | The state nonce is stored server-side to protect against CSRF.
    |
    */

    'oauth' => [
        'token_type' => env('SHOPIFY_TOKEN_TYPE', 'offline'),
        'nonce_ttl' => 600,
        'cache_prefix' => 'shopify_oauth_nonce',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The OAuth install/callback routes. Disable to register your own.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => env('SHOPIFY_ROUTE_PREFIX', 'shopify'),
        'middleware' => ['web'],
        'install' => 'install',
        'callback' => 'callback',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Client
    |--------------------------------------------------------------------------
    |
    | HTTP timeouts and the rate-limit retry/backoff policy. The cost buffer is
    | the minimum GraphQL bucket we keep in reserve before pre-emptively waiting.
    |
    */

    'api' => [
        'http' => [
            'timeout' => 15,
            'connect_timeout' => 5,
        ],
        'throttle' => [
            'max_retries' => 5,
            'base_delay_ms' => 500,
            'max_delay_ms' => 10_000,
            'cost_buffer' => 100,
        ],
        'rest_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks (Phase 2)
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'enabled' => false,
        'prefix' => 'shopify/webhooks',
        'middleware' => ['api'],
        'topics' => [
            // 'app/uninstalled' => \App\Listeners\HandleAppUninstalled::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing (Phase 3)
    |--------------------------------------------------------------------------
    */

    'billing' => [
        'enabled' => false,
        'required' => false,
        'plans' => [
            // 'pro' => ['price' => 19.99, 'interval' => 'EVERY_30_DAYS', 'trial_days' => 7],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('SHOPIFY_LOG_CHANNEL'),
    ],

];
