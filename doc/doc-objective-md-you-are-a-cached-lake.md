# Laravel Shopify Package — Phase 1 (Foundation)

## Context

This repo is a fresh `laravel/laravel` 13 app (PHP 8.3+, Pest 4.7, Pint, SQLite) with **no Shopify code** and an empty `packages/` dir. The objective ([doc/OBJECTIVE.md](doc/OBJECTIVE.md)) is to build a production-grade, distributable, multi-tenant Shopify integration package (GraphQL-first, SOLID, contracts + services + DTOs, queues, tests).

Decisions confirmed with the user:
- **Location:** self-contained package at `packages/laravel-shopify`, wired into the host app via a Composer **path repository** (so it's a real distributable package, runnable/testable in place).
- **Namespace:** `AmadulHaque\LaravelShopify\` → `src/`; composer name `amadulhaque/laravel-shopify`.
- **Frontend:** backend-only (no App Bridge / embedded UI).
- **Build:** map the full architecture; implement **Phase 1 foundation** now (skeleton, config, Store model, OAuth, GraphQL/REST client, DTOs, tests). Webhooks/billing/lifecycle/bulk/replay/plugins/observability are later phases with extension points reserved.

Outcome: a self-wiring package (auto-discovered provider + `Shopify` facade) that installs a multi-tenant store via Shopify OAuth and makes authenticated, rate-limit-aware GraphQL calls per store, covered by Pest tests.

## Key decisions

- **API version:** default `2025-07`, configurable via `shopify.api_version`. GraphQL is the primary path; REST is fallback only.
- **Token:** offline access token, stored on the model with Eloquent `encrypted` cast (column type `text`).
- **OAuth state/nonce:** stored in **cache** (driver-agnostic; works in tests where session is `array`), keyed by nonce with short TTL, pulled atomically on callback.
- **Store model** ships in the package, implements a `ShopModel` contract, and is swappable via `config('shopify.store.model')`. Table `shopify_stores`.
- **Testing:** add `orchestra/testbench` as package `require-dev` only; package has its own `phpunit.xml` + Pest. Host `composer test` unchanged.
- **PHP syntax:** keep to 8.3-compatible features (promotion, enums, `readonly` classes/props). Avoid 8.4-only property hooks / asymmetric visibility so `php: ^8.3` stays valid.

## File tree — `packages/laravel-shopify/`

```
composer.json, phpunit.xml
config/shopify.php
database/migrations/2025_01_01_000000_create_shopify_stores_table.php
database/factories/StoreFactory.php
routes/shopify.php
src/
  Providers/ShopifyServiceProvider.php
  Facades/Shopify.php
  Shopify.php                         # manager the facade proxies to
  Contracts/{ShopifyApiClient,ClientFactory,StoreRepository,ShopModel,
             WebhookRegistry,BillingManager}.php   # last two = interface-only stubs
  Models/Store.php
  Enums/TokenType.php
  Repositories/EloquentStoreRepository.php
  Services/{GraphQlClient,ShopifyClientFactory}.php
  Support/{Hmac,Nonce,ShopDomain,ThrottleBackoff}.php
  DTOs/{GraphQlResponse,RestResponse,AccessToken,ThrottleStatus,OAuthCallbackData}.php
  ValueObjects/Scopes.php
  Actions/{BuildAuthorizeUrl,ExchangeCodeForToken}.php
  Http/Controllers/{InstallController,CallbackController}.php
  Http/Middleware/{VerifyShopifyOAuth,VerifyShopifyWebhook}.php  # webhook = P2 stub
  Events/{StoreInstalled,StoreUninstalled}.php
  Console/InstallCommand.php
  Exceptions/{ShopifyException,ShopifyApiException,ThrottledException,
              OAuthException,InvalidWebhookException}.php
  Jobs/ Listeners/ Webhooks/ Billing/   # .gitkeep — later phases
tests/
  Pest.php, TestCase.php
  Feature/{OAuthInstallRedirectTest,OAuthCallbackTest,GraphQlClientThrottleTest}.php
  Unit/{HmacVerificationTest,GraphQlResponseDtoTest,ScopesValueObjectTest}.php
```

## Wiring (composer)

**Package `composer.json`:** name `amadulhaque/laravel-shopify`, type `library`, require `php ^8.3` + `illuminate/{contracts,support,http,database} ^13.0` (not `laravel/framework`, for host-version tolerance). require-dev: `orchestra/testbench ^11`, `pestphp/pest ^4.7`, `pestphp/pest-plugin-laravel ^4.1`, `laravel/pint`, `mockery/mockery`. autoload PSR-4 `AmadulHaque\LaravelShopify\` → `src/` and factories namespace → `database/factories/`; autoload-dev `...\Tests\` → `tests/`. `extra.laravel.providers` = `ShopifyServiceProvider`, `extra.laravel.aliases` = `{Shopify: ...Facades\Shopify}`. `config.allow-plugins.pestphp/pest-plugin: true`.

**Root [composer.json](composer.json):** add
```json
"repositories": [{ "type": "path", "url": "packages/laravel-shopify", "options": { "symlink": true } }]
```
and `"amadulhaque/laravel-shopify": "*"` to `require`. Then `composer update amadulhaque/laravel-shopify` symlinks it and `package:discover` auto-registers the provider + facade. No `bootstrap/providers.php` change needed.

## Migration — `shopify_stores`

`id`; `shop_domain` string **unique**; `access_token` text nullable (encrypted); `token_type` string default `offline`; `scopes` text nullable; `plan` string nullable; `shop_id` unsignedBigInteger nullable (index); `email`/`country`/`currency` string nullable; `installed_at` / `uninstalled_at` timestamps nullable (index `uninstalled_at`); `metadata` json nullable; timestamps.

## config/shopify.php (key groups)

`api_key`, `api_secret`, `api_version` (`2025-07`), `scopes`, `app_url`; `store.{model,repository,table}`; `oauth.{token_type,nonce_store,nonce_ttl,cache_prefix}`; `routes.{enabled,prefix,middleware,install,callback}`; `api.{http.timeout, throttle.{max_retries,base_delay_ms,max_delay_ms,cost_buffer}, rest_fallback}`; placeholders `webhooks.*` and `billing.*` (disabled, present so config is stable for later phases); `logging.channel`.

## Core class responsibilities

**Contracts** — `ShopifyApiClient`: `forStore(ShopModel): static`, `graphql(string,array=[]): GraphQlResponse`, `rest(string,string,array=[]): RestResponse`. `ClientFactory`: `make(ShopModel): ShopifyApiClient`, `default(): ShopifyApiClient`. `ShopModel`: `getShopDomain/getAccessToken/getScopes/isActive`. `StoreRepository`: `findByDomain`, `upsertFromOAuth(string, AccessToken): ShopModel`, `markUninstalled`. `WebhookRegistry`/`BillingManager`: interface-only stubs for P2/P3.

**`Services\GraphQlClient`** (the heart) — constructed with `HttpFactory`, config array, `LoggerInterface`. `forStore()` returns a **clone** carrying `$store` (immutable, no cross-tenant token bleed). Requests POST `https://{shop}/admin/api/{version}/graphql.json` with header `X-Shopify-Access-Token`. **Retry loop:** detect throttle via `errors[].extensions.code === 'THROTTLED'` (GraphQL) or `429`+`Retry-After`/call-limit header (REST), and pre-emptively via `extensions.cost.throttleStatus` (`currentlyAvailable < cost_buffer`). On throttle and `attempt < max_retries`, wait `ThrottleBackoff::delayMs()` (bucket restore math when available, else exponential + jitter) using `Illuminate\Support\Sleep` (fakeable in tests), then retry. Exhaustion → `ThrottledException`. GraphQL `userErrors`/`errors` are returned in the DTO (caller decides); only transport/throttle failures throw. `rest()` mirrors the loop with REST throttle signals.

**`Services\ShopifyClientFactory`** — singleton; builds `GraphQlClient` from container, `make()` calls `forStore()`.

**DTOs** (all `final readonly`) — `GraphQlResponse{data,errors,cost:?ThrottleStatus,status}` + `fromHttp()`, `hasErrors()`, `throttled()`. `ThrottleStatus` from `extensions.cost.throttleStatus` + `needsBackoff(buffer)`, `secondsToRestore(needed)`. `AccessToken{token,scopes,type}` + `fromResponse()`. `RestResponse{body,status,headers}` + `retryAfter()`. `OAuthCallbackData` from request.

**`ValueObjects\Scopes`** — `fromString/toString/has/diff/equals` (diff/equals reserved for later scope-change detection).

**Support** — `Hmac::verifyOAuth(query,secret)` (strip hmac, sort, build query string, `hash_hmac` sha256, **`hash_equals`** constant-time) and `verifyWebhook(rawBody,header,secret)` (base64, P2). `Nonce` generate/store(cache)/pull(atomic get+forget), driver from config. `ShopDomain::normalize/isValid` (regex `^[a-z0-9][a-z0-9\-]*\.myshopify\.com$`) to block SSRF/open-redirect. `ThrottleBackoff::delayMs(attempt,base,max,?status)`.

**Actions** — `BuildAuthorizeUrl(shop,nonce)` builds `/admin/oauth/authorize?...&state=nonce&grant_options[]=`. `ExchangeCodeForToken(shop,code): AccessToken` POSTs `/admin/oauth/access_token`, throws `OAuthException` on non-2xx.

**Controllers (thin)** — `InstallController`: validate shop, generate+store nonce, `redirect()->away(BuildAuthorizeUrl)`. `CallbackController`: HMAC verified by middleware; verify nonce (`Nonce::pull(state) === shop`), exchange token, `upsertFromOAuth`, `event(new StoreInstalled($store))`, redirect/200; nonce/hmac failure → 403.

**Middleware** — `VerifyShopifyOAuth` (HMAC, abort 403), aliased `shopify.oauth`; `VerifyShopifyWebhook` aliased `shopify.webhook` (P2). Both registered in provider `boot()`.

**Provider** — `register()`: `mergeConfigFrom`; singleton `'shopify'` → `Shopify`; bind `ClientFactory`→`ShopifyClientFactory` (singleton), `ShopifyApiClient`→`factory->default()`, `StoreRepository`→ class from config. `boot()`: `loadMigrationsFrom`, `loadRoutesFrom` (if enabled), alias middleware, and in console: publish `shopify-config` / `shopify-migrations` / `shopify-models` tags + register `InstallCommand`.

**`routes/shopify.php`** — prefix/middleware from config; `GET install` (`InstallController`, `shopify.oauth`, name `shopify.install`), `GET callback` (`CallbackController`, `shopify.oauth`, name `shopify.callback`).

**Facade/DX** — `Shopify::api()->forStore($store)->graphql($q, $v)`; `Shopify::store($domain)`.

## Tests (Pest + Testbench, package-local)

`TestCase extends Orchestra\Testbench\TestCase`: `getPackageProviders` → `[ShopifyServiceProvider::class]`; `getEnvironmentSetUp` sets api key/secret/scopes, `app.key` (for encrypted cast), in-memory sqlite; `use RefreshDatabase`.

1. **OAuthInstallRedirectTest** — valid HMAC install → 302 to `{shop}` with `client_id`/`scope`/`redirect_uri`/`state`; nonce written to cache; non-myshopify shop → 403.
2. **OAuthCallbackTest** — `Http::fake` token exchange; pre-seed nonce; valid HMAC+state → `shopify_stores` row with decrypted `access_token`, `installed_at` set; `Event::fake` saw `StoreInstalled`. Negatives: wrong state → 403/no row; tampered hmac → 403.
3. **GraphQlClientThrottleTest** — `Http::sequence` THROTTLED-then-success with `Sleep::fake`; assert success data, `assertSentCount(2)`, `Sleep::assertSlept`. All-throttled → `ThrottledException`. REST `429`+`Retry-After` then 200 honored.
4. **HmacVerificationTest** — OAuth + webhook HMAC true/false against fixtures; constant-time path.
5. **GraphQlResponseDtoTest** + **ScopesValueObjectTest** — DTO mapping (`data`/`errors`/`cost`/`throttled`/`secondsToRestore`), `Scopes` has/equals/diff, Store encrypted-cast + scopes roundtrip.

Mocking: `Http::fake` keyed by globs (`*/admin/api/*/graphql.json`, `*/admin/oauth/access_token`), `Http::sequence` for retries, `Sleep::fake`/`assertSlept` for backoff, `Event::fake`, array cache for nonce.

## Later phases (extension points reserved now)

| Phase | Slots into | Reserved hook |
|---|---|---|
| Webhooks (P2) | `Webhooks/`, `WebhookController`, `VerifyShopifyWebhook` | `WebhookRegistry`, `Hmac::verifyWebhook`, `InvalidWebhookException`, `config.webhooks`, middleware alias; add `webhook_events` table (idempotency on `X-Shopify-Webhook-Id`) |
| Billing (P3) | `Billing/{RecurringCharge,UsageCharge}` | `BillingManager` contract, `config.billing`, client `graphql/rest` |
| Lifecycle (P4) | `Listeners/`, `app/uninstalled` → `markUninstalled` | `StoreUninstalled` event, `uninstalled_at` column, repo method |
| Bulk ops (P5) | `Jobs/RunBulkOperation`, `Actions/StartBulkOperation` | `bulkOperationRunQuery` via client; queue driver-agnostic |
| Replay (P6) | `webhook_events` + `shopify:webhooks:replay` | idempotency table from P2 |
| Plugins (P7) | `ShopifyPlugin` contract + tagged bindings | `metadata` json column, contracts dir, manager pattern |
| Observability (P8) | `Listeners/`, `config.logging.channel` | logger injected into client; `ThrottleStatus` captures cost telemetry |

## Build order & verification

1. Package `composer.json` + root path-repo wiring → `composer update amadulhaque/laravel-shopify`; confirm `php artisan package:discover` lists the provider and `Shopify` alias resolves.
2. Provider + config + facade + `Shopify` manager → `php artisan config:show shopify` and `php artisan vendor:publish --tag=shopify-config`.
3. Store model + migration + factory + repository → `php artisan migrate` (host) / `database-schema` shows `shopify_stores`.
4. OAuth (actions, controllers, middleware, routes, events) → `php artisan route:list --path=shopify`.
5. GraphQL/REST client + DTOs + support + exceptions.
6. `shopify:install` command.
7. Pest tests alongside each unit; run `cd packages/laravel-shopify && vendor/bin/pest` (package suite) — all green.
8. `vendor/bin/pint --dirty --format agent` on changed PHP. Host `php artisan test --compact` stays green.

End-to-end manual check (after implementation): set `SHOPIFY_API_KEY/SECRET/SCOPES` in `.env`, hit `/shopify/install?shop=<dev-store>.myshopify.com` against a Shopify dev app, complete OAuth, confirm a `shopify_stores` row with an encrypted token, then `Shopify::api()->forStore($store)->graphql('{ shop { name } }')` returns the shop name.
