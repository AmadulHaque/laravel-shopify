<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Models\Store;
use AmadulHaque\LaravelShopify\Repositories\EloquentStoreRepository;
use AmadulHaque\LaravelShopify\Webhooks\Handlers\HandleAppSubscriptionUpdate;
use AmadulHaque\LaravelShopify\Webhooks\Handlers\HandleAppUninstalled;

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
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Inbound webhooks are verified (HMAC), persisted for idempotency, then
    | processed on a queue. Map each topic to a handler implementing the
    | WebhookHandler contract; you can also listen for the WebhookReceived event.
    |
    */

    'webhooks' => [
        'enabled' => true,
        'prefix' => env('SHOPIFY_WEBHOOK_PREFIX', 'shopify/webhooks'),
        'middleware' => ['api'],
        'table' => 'shopify_webhook_events',

        // Register the configured webhooks automatically after a store installs.
        'auto_register' => env('SHOPIFY_WEBHOOK_AUTO_REGISTER', false),

        'queue' => [
            'connection' => env('SHOPIFY_WEBHOOK_QUEUE_CONNECTION'),
            'queue' => env('SHOPIFY_WEBHOOK_QUEUE', 'shopify-webhooks'),
            'tries' => 5,
            'backoff' => [10, 30, 60, 120, 300],
        ],

        'topics' => [
            'app/uninstalled' => HandleAppUninstalled::class,
            'app_subscriptions/update' => HandleAppSubscriptionUpdate::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | In-app billing via the GraphQL Billing API. Define plans with a recurring
    | price and an optional usage component. Enforce a plan with the
    | `shopify.billing[:plan]` middleware or Shopify::billing()->requirePlan().
    |
    */

    'billing' => [
        'enabled' => false,
        'required' => false,
        'default_plan' => env('SHOPIFY_BILLING_DEFAULT_PLAN', 'pro'),

        // Create test charges (no real money) — keep true in dev/staging.
        'test' => env('SHOPIFY_BILLING_TEST', true),

        'routes' => [
            'enabled' => true,
            'prefix' => env('SHOPIFY_BILLING_PREFIX', 'shopify/billing'),
            'middleware' => ['web'],
            'confirm' => 'confirm',           // => /shopify/billing/confirm (return_url)
            'redirect_after' => '/',          // where to send the merchant after activation
        ],

        'plans' => [
            'pro' => [
                'price' => 19.99,
                'currency' => 'USD',
                'interval' => 'EVERY_30_DAYS',    // or ANNUAL
                'trial_days' => 7,
                'usage' => [                      // optional usage-based component
                    'capped_amount' => 50.00,
                    'terms' => '$0.10 per extra order processed',
                ],
            ],
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
