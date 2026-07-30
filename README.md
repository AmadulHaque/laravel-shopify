# Decoupled Laravel Shopify

A framework-style Shopify integration for Laravel. It owns Shopify protocol work—OAuth, Admin GraphQL, webhook subscriptions, and HMAC verification—while the host application owns shops, users, tokens, persistence, tenancy, and authorization.

It has **no** models, migrations, database access, sessions, queues, cache stores, Redis dependency, authentication guards, or storage implementation.

## Contents

- [Install](#install)
- [Configure](#configure)
- [Bind application contracts](#bind-application-contracts)
- [OAuth install flow](#oauth-install-flow)
- [GraphQL usage](#graphql-usage)
- [Middleware](#middleware)
- [Webhooks](#webhooks)
- [Events](#events)
- [Testing](#testing)

## Install

Install the package from Packagist:

```bash
composer require decoupled/laravel-shopify
```

Laravel discovers `ShopifyServiceProvider` automatically. Publish the configuration only when you need to customize it:

```bash
php artisan vendor:publish --tag=shopify-config
```

For this repository's local development package, Composer already uses `packages/Shopify` as a path repository, so no additional install command is needed.

## Configure

Set these values in the host application's `.env`:

```dotenv
SHOPIFY_CLIENT_ID=
SHOPIFY_CLIENT_SECRET=
SHOPIFY_REDIRECT_URI=https://app.example.com/shopify/callback
# Redis, file, database, etc. Omit to use Laravel CACHE_STORE.
SHOPIFY_STATE_CACHE_STORE=redis
SHOPIFY_API_VERSION=2026-07
SHOPIFY_SCOPES=read_products,write_products
```

Add the same complete callback URL to your Shopify app's allowed redirection URLs. The published `config/shopify.php` configures API version, scopes, retries, timeout, and a replaceable HTTP client. It does not configure storage.

### OAuth state data

Pass optional state data as third `redirect()` or `authorizationUrl()` argument. Package sends only an opaque random state token to Shopify, stores your data through `OAuthStateRepository`, then returns it as `$result->state` after valid callback. Do not JSON-encode or URL-encode state yourself.

```php
return Shopify::oauth()->redirect($shop, route('shopify.callback'), [
    'id' => 1234,
]);

// After exchange:
$result->state['id'];
```

## Bind application contracts

OAuth state uses package cache repository by default. Set `SHOPIFY_STATE_CACHE_STORE=redis` or `file` (or omit it to use Laravel `CACHE_STORE`). Bind your own `OAuthStateRepository` only when custom state storage is needed. Bind your own implementations for tokens, shop resolution, webhook definitions, and application authorization.

```php
// app/Providers/AppServiceProvider.php

use App\Shopify\MongoOAuthStateRepository;
use App\Shopify\SubdomainShopResolver;
use App\Shopify\TenantTokenRepository;
use App\Shopify\TenantWebhookRepository;
use Decoupled\Shopify\Contracts\OAuthStateRepository;
use Decoupled\Shopify\Contracts\ShopResolver;
use Decoupled\Shopify\Contracts\TokenRepository;
use Decoupled\Shopify\Contracts\WebhookRepository;

public function register(): void
{
    $this->app->bind(OAuthStateRepository::class, MongoOAuthStateRepository::class);
    $this->app->bind(TokenRepository::class, TenantTokenRepository::class);
    $this->app->bind(ShopResolver::class, SubdomainShopResolver::class);
    $this->app->bind(WebhookRepository::class, TenantWebhookRepository::class);
}
```

`OAuthStateRepository::put()` receives optional state data. `pull()` must return it and consume state once. `TokenRepository::findFor(Shop $shop)` must return `AccessToken|null`. The package never calls a `save`, `create`, or `update` method on a repository.

### Optional application authorization

Bind `AuthorizationResolver` when your application needs to decide whether its current caller may access a resolved shop. It can use Laravel Auth, a JWT, an API key, or any custom tenant system. If it is not bound, `shopify.auth` only verifies that a shop and Shopify token resolve.

```php
use App\Shopify\CurrentUserShopAuthorization;
use Decoupled\Shopify\Contracts\AuthorizationResolver;

$this->app->bind(AuthorizationResolver::class, CurrentUserShopAuthorization::class);
```

## OAuth install flow

The package does not register routes because URL structure is an application decision. Define your own routes:

```php
use App\Http\Controllers\ShopifyInstallController;
use Illuminate\Support\Facades\Route;

Route::get('/shopify/install', [ShopifyInstallController::class, 'install']);
Route::get('/shopify/callback', [ShopifyInstallController::class, 'callback'])
    ->name('shopify.callback');
```

Example controller. `ShopifyInstallationStore` below is an **application** service; define it however your product stores installations.

```php
namespace App\Http\Controllers;

use App\Shopify\ShopifyInstallationStore;
use Decoupled\Shopify\Facades\Shopify;
use Illuminate\Http\Request;

class ShopifyInstallController
{
    public function install(Request $request)
    {
        return Shopify::oauth()->redirect($request->string('shop')->toString());
    }

    public function callback(Request $request, ShopifyInstallationStore $installations)
    {
        $result = Shopify::oauth()->exchange($request);

        // Your application decides where and how this is persisted/encrypted.
        $installations->save($result->shop, $result->token);

        return to_route('dashboard');
    }
}
```

```text
Merchant -> Host app: GET /shopify/install?shop=demo.myshopify.com
Host app -> OAuthStateRepository: put(random state, shop, expiry)
Host app -> Shopify: redirect to authorization URL
Shopify -> Host app: callback(code, state, hmac, shop)
Host app -> OAuthStateRepository: pull(state) [one time]
Host app -> Shopify: exchange code for token
Host app -> Application store: save(shop, token)
```

`exchange()` returns an immutable `OAuthResult` with `Shop` and `AccessToken`; it never persists either value.

## GraphQL usage

GraphQL resolves the access token from `TokenRepository`, so callers only provide a shop.

```php
use Decoupled\Shopify\Facades\Shopify;

$response = Shopify::graph()
    ->shop('demo.myshopify.com')
    ->query('query ($id: ID!) { product(id: $id) { id title } }', [
        'id' => 'gid://shopify/Product/1',
    ]);

if ($response->hasErrors()) {
    return response()->json(['errors' => $response->errors()], 422);
}

return response()->json($response->data());
```

Mutations use the same fluent API:

```php
Shopify::graph()
    ->shop($shop)
    ->mutation('mutation ($input: ProductInput!) { productCreate(input: $input) { product { id } userErrors { message } } }', [
        'input' => ['title' => 'New product'],
    ]);
```

`GraphqlResponse` preserves Shopify `data` and `errors`. Transport failures throw `ShopifyHttpException`. A GraphQL throttle response throws `GraphqlThrottled`; queue jobs should release using `$exception->retryAfterSeconds` rather than immediately retrying.

To replace Laravel's HTTP client, bind `Decoupled\Shopify\Contracts\HttpClient` to your adapter. This is where applications can add tracing, circuit breaking, distributed rate limiting, or custom retry behavior.

## Middleware

The package provides three aliases:

```php
Route::get('/shop', ShopController::class)->middleware('shopify.shop');
Route::get('/shop/data', DataController::class)->middleware('shopify.auth');
Route::post('/webhooks/shopify', ShopifyWebhookController::class)->middleware('shopify.webhook');
```

- `shopify.shop`: resolves a `Shop` via `ShopResolver` and attaches it as `shopify.shop`.
- `shopify.auth`: resolves the shop, requires a token from `TokenRepository`, and optionally invokes `AuthorizationResolver`.
- `shopify.webhook`: verifies Shopify's HMAC and attaches a typed `Webhook` as `shopify.webhook`.

An authenticated Laravel route can combine normal application auth with Shopify authorization without coupling the package to a User model:

```php
Route::get('/settings', SettingsController::class)
    ->middleware(['auth', 'shopify.auth']);
```

## Webhooks

Provide the desired subscriptions from your application's `WebhookRepository`, then manage subscriptions through GraphQL:

```php
use Decoupled\Shopify\Facades\Shopify;
use Decoupled\Shopify\Webhooks\WebhookSubscription;

Shopify::webhooks()->register($shop, new WebhookSubscription(
    'orders/create',
    'https://app.example.com/webhooks/orders',
));

Shopify::webhooks()->delete($shop, $subscriptionId);
Shopify::webhooks()->sync($shop);       // Registers missing desired subscriptions.
Shopify::webhooks()->sync($shop, true); // Also removes stale remote subscriptions.
```

`sync(..., true)` is explicitly opt-in because deleting a remote subscription can affect another component that manages the same store.

Webhook handling stays thin:

```php
public function __invoke(Request $request)
{
    $webhook = $request->attributes->get('shopify.webhook');

    ProcessShopifyWebhook::dispatch($webhook);

    return response()->noContent();
}
```

```text
Shopify -> Host app: headers + raw JSON body
Host app -> VerifyWebhook: validate HMAC and required headers
VerifyWebhook -> Request: attach shopify.webhook value object
VerifyWebhook -> Events: WebhookReceived, WebhookVerified
Host app -> Application logic: queue/process payload
```

## Events

Listen in the host application for package events:

```php
use Decoupled\Shopify\Events\ShopInstalled;
use Illuminate\Support\Facades\Event;

Event::listen(ShopInstalled::class, function (ShopInstalled $event) {
    // Persist the result, initialize a tenant, or enqueue onboarding work.
});
```

Available events: `ShopInstalled`, `AccessTokenUpdated`, `WebhookReceived`, `WebhookVerified`, and `ShopUninstalled`.

## Testing

Bind in-memory implementations of the contracts and fake Shopify HTTP requests:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'https://demo.myshopify.com/admin/api/*/graphql.json' => Http::response([
        'data' => ['shop' => ['name' => 'Demo']],
    ]),
]);
```

The package test suite covers OAuth exchange, token resolution, GraphQL requests, middleware aliases, HMAC rejection and verification, webhook events, the facade, and webhook registration. Run it with:

```bash
php artisan test
```

## Public API

```php
Shopify::oauth()->redirect($shop);
Shopify::oauth()->exchange($request);
Shopify::graph()->shop($shop)->query($query, $variables);
Shopify::graph()->shop($shop)->mutation($mutation, $variables);
Shopify::webhooks()->register($shop, $subscription);
Shopify::webhooks()->delete($shop, $subscriptionId);
Shopify::webhooks()->sync($shop);
Shopify::client();
```
