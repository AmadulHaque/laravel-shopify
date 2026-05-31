# Laravel Shopify

A production-grade, multi-tenant Shopify integration package for Laravel 13. GraphQL-first, built on clean architecture (contracts + services + DTOs + actions), queue-driven, and fully tested.

- **Package:** `amadulhaque/laravel-shopify`
- **Namespace:** `AmadulHaque\LaravelShopify`
- **Requires:** PHP 8.3+ · Laravel 13 · a queue + cache driver (Redis recommended in production)
- **Status:** Phases 1–3 implemented (Auth, API client, Webhooks, Billing) — 60 passing tests.

---

## Table of contents

1. [Features](#features)
2. [Architecture](#architecture)
3. [Installation & implementation](#installation--implementation)
4. [Configuration](#configuration)
5. [Usage](#usage)
   - [OAuth & store management](#1-oauth--store-management)
   - [API client (GraphQL-first)](#2-api-client-graphql-first)
   - [Webhooks](#3-webhooks)
   - [Billing](#4-billing)
6. [Use cases](#use-cases)
7. [Artisan commands](#artisan-commands)
8. [Events](#events)
9. [Testing](#testing)
10. [Roadmap](#roadmap)

---

## Features

| Area | What you get |
|------|--------------|
| **Auth & multi-tenancy** | Shopify OAuth 2.0 install/callback, HMAC + CSRF-nonce verification, encrypted per-store token storage, swappable `Store` model |
| **API client** | GraphQL-first client with REST fallback, per-store auth, automatic rate-limit retry/backoff (cost-bucket aware), typed DTO responses |
| **Webhooks** | Auto-registration, HMAC verification, queue processing, idempotency, retries, dead-lettering, event-driven dispatch |
| **Billing** | GraphQL Billing API — recurring **and** usage-based plans, enforcement middleware, plan management, `requirePlan()` DX |
| **DX** | `Shopify` facade, publishable config, Artisan commands, events + contracts for everything |
| **Quality** | SOLID, typed DTOs (no raw arrays), `declare(strict_types=1)` throughout, Pest test suite, Pint-formatted |

---

## Architecture

The package follows a layered, dependency-inverted design. Every major capability sits behind a contract so it can be swapped or mocked.

```
src/
├── Contracts/        ShopifyApiClient, ClientFactory, StoreRepository, ShopModel,
│                     StoreResolver, WebhookRegistry, WebhookHandler, BillingManager
├── Services/         GraphQlClient (rate-limit retry loop), ShopifyClientFactory
├── DTOs/             GraphQlResponse, RestResponse, ThrottleStatus, AccessToken,
│                     OAuthCallbackData, WebhookPayload, Subscription, …
├── Actions/          BuildAuthorizeUrl, ExchangeCodeForToken, RegisterWebhooks,
│                     CreateSubscription, FetchActiveSubscription, RecordUsage
├── Jobs/             ProcessWebhook (queued, idempotent, dead-letter)
├── Events/           StoreInstalled, StoreUninstalled, WebhookReceived,
│                     SubscriptionActivated/Declined/Cancelled
├── Webhooks/         ConfigWebhookRegistry, Handlers/*
├── Billing/          ShopifyBillingManager, PlanRepository, Plan
├── Http/             Controllers/* + Middleware (shopify.oauth/webhook/billing)
├── Models/           Store, WebhookEvent
├── Repositories/     EloquentStoreRepository
├── Support/          Hmac, Nonce, ShopDomain, ThrottleBackoff, CurrentStore, …
├── Console/          shopify:install, shopify:webhook:register, shopify:billing:setup
├── Facades/Shopify   → AmadulHaque\LaravelShopify\Shopify (manager)
└── Providers/        ShopifyServiceProvider (auto-discovered)
```

**Principles:** the OAuth/API/billing layers depend on `ShopModel`/`StoreRepository` contracts, never the concrete Eloquent model — so you can point the package at your own model via config. Heavy work (webhooks) runs on queues. All Shopify responses come back as readonly DTOs.

---

## Installation & implementation

### 1. Require the package

This repository develops the package in-place via a Composer **path repository** (`packages/laravel-shopify`). The root `composer.json` already contains:

```json
"repositories": [
    { "type": "path", "url": "packages/laravel-shopify", "options": { "symlink": true } }
],
"require": {
    "amadulhaque/laravel-shopify": "@dev"
}
```

For an external project, you would instead:

```bash
composer require amadulhaque/laravel-shopify
```

The service provider and `Shopify` facade are **auto-discovered** — no manual registration.

### 2. Publish config & run migrations

```bash
php artisan shopify:install        # publishes config + migrations, prints next steps
php artisan migrate                # creates shopify_stores + shopify_webhook_events
```

`shopify:install` is a convenience wrapper around:

```bash
php artisan vendor:publish --tag=shopify-config
php artisan vendor:publish --tag=shopify-migrations
```

> Migrations also auto-load from the package, so `php artisan migrate` works even without publishing them. Publish only if you want to customise the schema.

### 3. Set environment variables

```dotenv
SHOPIFY_API_KEY=your-app-api-key
SHOPIFY_API_SECRET=your-app-api-secret
SHOPIFY_SCOPES=read_products,write_products,read_orders
SHOPIFY_APP_URL=https://your-app.example.com

# optional
SHOPIFY_API_VERSION=2025-07
SHOPIFY_TOKEN_TYPE=offline
SHOPIFY_WEBHOOK_AUTO_REGISTER=true
SHOPIFY_BILLING_TEST=true
```

### 4. Point Shopify at your callback

In the Shopify Partner dashboard set the allowed redirection URL to:

```
{SHOPIFY_APP_URL}/shopify/callback
```

That's it — visiting `/shopify/install?shop=your-store.myshopify.com` now starts the OAuth flow.

---

## Configuration

`config/shopify.php` (excerpt of the key groups):

```php
return [
    'api_key'     => env('SHOPIFY_API_KEY'),
    'api_secret'  => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),
    'scopes'      => env('SHOPIFY_SCOPES', 'read_products,write_products'),
    'app_url'     => env('SHOPIFY_APP_URL', env('APP_URL')),

    'store' => [
        'model'      => \AmadulHaque\LaravelShopify\Models\Store::class, // swap for your own
        'repository' => \AmadulHaque\LaravelShopify\Repositories\EloquentStoreRepository::class,
        'table'      => 'shopify_stores',
    ],

    'oauth' => [
        'token_type'   => env('SHOPIFY_TOKEN_TYPE', 'offline'),
        'nonce_ttl'    => 600,
        'cache_prefix' => 'shopify_oauth_nonce',
    ],

    'routes' => [
        'enabled'    => true,
        'prefix'     => 'shopify',
        'middleware' => ['web'],
        'install'    => 'install',
        'callback'   => 'callback',
    ],

    'api' => [
        'http'     => ['timeout' => 15, 'connect_timeout' => 5],
        'throttle' => ['max_retries' => 5, 'base_delay_ms' => 500, 'max_delay_ms' => 10_000, 'cost_buffer' => 100],
        'rest_fallback' => true,
    ],

    'webhooks' => [
        'enabled'       => true,
        'prefix'        => 'shopify/webhooks',
        'middleware'    => ['api'],
        'table'         => 'shopify_webhook_events',
        'auto_register' => env('SHOPIFY_WEBHOOK_AUTO_REGISTER', false),
        'queue'         => ['connection' => null, 'queue' => 'shopify-webhooks', 'tries' => 5, 'backoff' => [10, 30, 60, 120, 300]],
        'topics'        => [
            'app/uninstalled'          => \AmadulHaque\LaravelShopify\Webhooks\Handlers\HandleAppUninstalled::class,
            'app_subscriptions/update' => \AmadulHaque\LaravelShopify\Webhooks\Handlers\HandleAppSubscriptionUpdate::class,
        ],
    ],

    'billing' => [
        'enabled'      => false,                 // opt-in
        'required'     => false,
        'default_plan' => 'pro',
        'test'         => env('SHOPIFY_BILLING_TEST', true),
        'routes'       => ['enabled' => true, 'prefix' => 'shopify/billing', 'middleware' => ['web'], 'confirm' => 'confirm', 'redirect_after' => '/'],
        'plans'        => [
            'pro' => [
                'price' => 19.99, 'currency' => 'USD', 'interval' => 'EVERY_30_DAYS', 'trial_days' => 7,
                'usage' => ['capped_amount' => 50.00, 'terms' => '$0.10 per extra order processed'],
            ],
        ],
    ],
];
```

> **Note:** when you upgrade the package, re-run `php artisan vendor:publish --tag=shopify-config --force` (or merge new keys) so a previously published config picks up new options like the `billing` block.

---

## Usage

The `Shopify` facade is the entry point:

| Call | Returns |
|------|---------|
| `Shopify::api()` | unbound API client — call `->forStore($store)` |
| `Shopify::for($store)` | API client already bound to a store |
| `Shopify::store($domain)` | the persisted store model, or `null` |
| `Shopify::webhooks()` | the webhook registry |
| `Shopify::billing()` | the billing manager |
| `Shopify::currentStore()` | the store the current request acts on |
| `Shopify::resolveStoreUsing($cb)` | override how the current store is resolved |

### 1. OAuth & store management

Routes are registered automatically:

| Method | URI | Name |
|--------|-----|------|
| GET | `/shopify/install?shop=…` | `shopify.install` |
| GET | `/shopify/callback` | `shopify.callback` |

The flow: install redirects the merchant to Shopify's consent screen (with a cached CSRF nonce); the callback verifies HMAC + nonce, exchanges the code for an offline token, stores it **encrypted**, and fires `StoreInstalled`.

```php
use AmadulHaque\LaravelShopify\Events\StoreInstalled;

class SeedNewStore
{
    public function handle(StoreInstalled $event): void
    {
        $store = $event->store;            // implements ShopModel
        // dispatch an initial product sync, send a welcome email, etc.
    }
}
```

The `Store` model is multi-tenant (one row per `shop_domain`) with an `encrypted` access-token cast. Swap it for your own model via `config('shopify.store.model')` — just implement the `ShopModel` contract.

### 2. API client (GraphQL-first)

```php
use AmadulHaque\LaravelShopify\Facades\Shopify;

$store = Shopify::store('demo.myshopify.com');

$response = Shopify::api()->forStore($store)->graphql(<<<'GQL'
    query {
        products(first: 10) {
            edges { node { id title } }
        }
    }
GQL);

if ($response->hasErrors()) {
    // $response->errors is a typed array of GraphQL errors
}

$products = $response->data['products']['edges'];   // typed GraphQlResponse DTO
```

**Mutations with variables:**

```php
$response = Shopify::for($store)->graphql(
    'mutation($input: ProductInput!) { productCreate(input: $input) { product { id } userErrors { message } } }',
    ['input' => ['title' => 'New product']],
);
```

**REST fallback** (for endpoints without GraphQL parity):

```php
$rest = Shopify::for($store)->rest('GET', 'orders.json', ['status' => 'open']);
$rest->successful();   // bool
$rest->body;           // decoded array
```

**Rate limiting is automatic.** The client reads Shopify's GraphQL cost bucket (`extensions.cost.throttleStatus`) and REST `429` / `Retry-After`, then retries with exponential backoff (or precise bucket-restore timing). After `max_retries` it throws `ThrottledException`. You never have to handle 429s yourself.

### 3. Webhooks

A single endpoint ingests every topic:

| Method | URI |
|--------|-----|
| POST | `/shopify/webhooks` |

**Pipeline:** `Verify HMAC → persist (idempotency) → queue job → fire event + run handler → dead-letter on failure`. The controller returns `200` immediately; all work happens in the queued `ProcessWebhook` job.

**Register a handler** — map a topic to a class implementing `WebhookHandler`:

```php
// config/shopify.php → webhooks.topics
'topics' => [
    'orders/create' => \App\Shopify\HandleOrderCreated::class,
],
```

```php
use AmadulHaque\LaravelShopify\Contracts\WebhookHandler;
use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;

final class HandleOrderCreated implements WebhookHandler
{
    public function handle(WebhookPayload $payload): void
    {
        $orderId = $payload->payload['id'];
        $shop    = $payload->shopDomain;
        // … your logic; runs inside the queued job
    }
}
```

Or register at runtime, and/or listen for the generic event for one-to-many fan-out:

```php
Shopify::webhooks()->register('orders/create', HandleOrderCreated::class);

// app/Providers/EventServiceProvider.php
use AmadulHaque\LaravelShopify\Events\WebhookReceived;

Event::listen(WebhookReceived::class, function (WebhookReceived $e) {
    if ($e->topic() === 'orders/create') { /* … */ }
});
```

**Auto-register** webhooks with Shopify after install by setting `SHOPIFY_WEBHOOK_AUTO_REGISTER=true`, or run it manually:

```bash
php artisan shopify:webhook:register --shop=demo.myshopify.com
```

**Reliability built in:** every webhook is stored in `shopify_webhook_events` keyed by `X-Shopify-Webhook-Id` (duplicates are ignored), retried per the `webhooks.queue` config, and marked `failed` (dead-lettered, never lost) once retries are exhausted.

The package ships a built-in `app/uninstalled` handler that deactivates the store and fires `StoreUninstalled`.

### 4. Billing

Billing is **opt-in** — set `shopify.billing.enabled = true` and define plans in config. It uses Shopify's GraphQL Billing API and supports both recurring and usage-based pricing.

**Enforce a plan on a route** (creates a subscription and redirects to Shopify's approval page if the store isn't subscribed):

```php
Route::middleware(['web', 'shopify.billing:pro'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

**…or enforce it in code** (the one-liner DX):

```php
public function index()
{
    Shopify::billing()->requirePlan('pro');   // redirects to confirmation if not subscribed
    // … reaches here only with an active 'pro' subscription
}
```

**Subscribe a store explicitly:**

```php
$confirmation = Shopify::billing()->subscribe($store, 'pro');

return redirect()->away($confirmation->confirmationUrl);   // merchant approves on Shopify
```

After approval Shopify redirects to `/shopify/billing/confirm`, which activates and persists the subscription and fires `SubscriptionActivated`.

**Usage-based billing:**

```php
Shopify::billing()->recordUsage($store, 'Processed 1 extra order', 0.10);
```

**Other operations:**

```php
Shopify::billing()->hasActivePlan($store, 'pro');   // fast, reads persisted state
Shopify::billing()->activeSubscription($store);     // live from Shopify
Shopify::billing()->cancel($store);
```

Subscription state stays in sync automatically via the built-in `app_subscriptions/update` webhook handler.

**Resolving the current store** for enforcement defaults to the `?shop=` parameter. Override it for session/auth-based apps:

```php
// e.g. in a service provider
Shopify::resolveStoreUsing(fn ($request) => $request->user()?->shopifyStore);
```

---

## Use cases

### Build a product-sync SaaS
Install via OAuth, listen for `StoreInstalled` to queue an initial sync, then use `Shopify::for($store)->graphql(...)` (rate-limit-safe) for ongoing reads/writes. React to `products/update` webhooks to keep your mirror fresh.

### Order-processing / fulfillment app
Register an `orders/create` webhook handler. Each order arrives as a queued, idempotent `WebhookPayload` — safe to retry, impossible to double-process. Failed orders dead-letter for inspection.

### Monetised app with a paywall
Define `pro`/`enterprise` plans in config, gate premium routes with `shopify.billing:pro`, and bill metered features with `recordUsage()`. Trials, capped usage amounts, and test charges are all config-driven.

### Multi-tenant agency dashboard
One app, many stores. Every API call, webhook, and subscription is scoped to a `Store` row; resolve the active store per request with `resolveStoreUsing()`.

### Clean uninstall / compliance
The built-in `app/uninstalled` handler revokes the token and fires `StoreUninstalled` — hook your data-cleanup logic onto that event.

---

## Artisan commands

| Command | Purpose |
|---------|---------|
| `shopify:install` | Publish config + migrations and print setup instructions |
| `shopify:webhook:register {--shop=}` | Register configured webhook topics with Shopify (all active stores, or one) |
| `shopify:billing:setup {--shop=} {--plan=}` | List configured plans, or subscribe a store and print the confirmation URL |

---

## Events

| Event | Fired when |
|-------|-----------|
| `StoreInstalled` | OAuth completes and a token is stored |
| `StoreUninstalled` | `app/uninstalled` webhook is processed |
| `WebhookReceived` | every verified webhook (inside the queue) |
| `SubscriptionActivated` | a subscription becomes active after approval |
| `SubscriptionDeclined` | merchant returns without an active subscription |
| `SubscriptionCancelled` | a subscription is cancelled / updated to inactive |

---

## Testing

The package has its own Pest + Orchestra Testbench suite (SQLite `:memory:`):

```bash
cd packages/laravel-shopify
composer install
vendor/bin/pest --compact
```

**60 tests / 186 assertions** cover OAuth redirect & callback, the GraphQL client's throttle-retry loop, webhook ingestion / idempotency / dead-lettering / registration, and the full billing flow (subscribe, enforcement, confirmation, usage, subscription webhook).

Testing your own app against the package is easy because everything is faked at the HTTP boundary:

```php
use Illuminate\Support\Facades\Http;

Http::fake(['*/graphql.json' => Http::response(['data' => ['shop' => ['name' => 'Acme']]])]);

$response = Shopify::for($store)->graphql('{ shop { name } }');
expect($response->data['shop']['name'])->toBe('Acme');
```

---

## Roadmap

Implemented: **Phase 1** (foundation/OAuth/API client), **Phase 2** (webhooks), **Phase 3** (billing).

Planned extension points are already reserved in the architecture:

| Phase | Feature |
|-------|---------|
| 4 | App lifecycle & data-cleanup strategy |
| 5 | GraphQL bulk operations (large product/order syncs) |
| 6 | Webhook replay (reprocess dead-lettered events) |
| 7 | Plugin/module system |
| 8 | Observability (request tracing, debug mode, metrics) |
