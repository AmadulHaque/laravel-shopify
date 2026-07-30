<?php

namespace Amadulhaque\Shopify\Providers;

use Amadulhaque\Shopify\Contracts\HttpClient;
use Amadulhaque\Shopify\Contracts\OAuthStateRepository;
use Amadulhaque\Shopify\Authentication\CacheOAuthStateRepository;
use Amadulhaque\Shopify\Authentication\RandomStateGenerator;
use Amadulhaque\Shopify\Authentication\StateGenerator;
use Amadulhaque\Shopify\Http\LaravelHttpClient;
use Amadulhaque\Shopify\Middleware\RequireShopAuthentication;
use Amadulhaque\Shopify\Middleware\ResolveShop;
use Amadulhaque\Shopify\Middleware\VerifyWebhook;
use Amadulhaque\Shopify\ShopifyManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ShopifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/shopify.php', 'shopify');
        $this->app->singleton(StateGenerator::class, RandomStateGenerator::class);
        $this->app->bindIf(OAuthStateRepository::class, CacheOAuthStateRepository::class);
        $this->app->singleton(HttpClient::class, fn ($app) => $app->make(config('shopify.http_client', LaravelHttpClient::class)));
        $this->app->singleton('shopify', ShopifyManager::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('shopify.shop', ResolveShop::class);
        $router->aliasMiddleware('shopify.auth', RequireShopAuthentication::class);
        $router->aliasMiddleware('shopify.webhook', VerifyWebhook::class);

        $this->publishes([
            __DIR__.'/../../config/shopify.php' => config_path('shopify.php'),
        ], 'shopify-config');
    }
}
