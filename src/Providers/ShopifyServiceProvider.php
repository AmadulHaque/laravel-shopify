<?php

namespace Decoupled\Shopify\Providers;

use Decoupled\Shopify\Contracts\HttpClient;
use Decoupled\Shopify\Contracts\OAuthStateRepository;
use Decoupled\Shopify\Authentication\CacheOAuthStateRepository;
use Decoupled\Shopify\Authentication\RandomStateGenerator;
use Decoupled\Shopify\Authentication\StateGenerator;
use Decoupled\Shopify\Http\LaravelHttpClient;
use Decoupled\Shopify\Middleware\RequireShopAuthentication;
use Decoupled\Shopify\Middleware\ResolveShop;
use Decoupled\Shopify\Middleware\VerifyWebhook;
use Decoupled\Shopify\ShopifyManager;
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
