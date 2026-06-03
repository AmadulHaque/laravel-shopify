<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Providers;

use AmadulHaque\LaravelShopify\Actions\RegisterWebhooks;
use AmadulHaque\LaravelShopify\Billing\ShopifyBillingManager;
use AmadulHaque\LaravelShopify\Console\BillingSetupCommand;
use AmadulHaque\LaravelShopify\Console\InstallCommand;
use AmadulHaque\LaravelShopify\Console\RegisterWebhooksCommand;
use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Contracts\StoreResolver;
use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\Events\StoreInstalled;
use AmadulHaque\LaravelShopify\Http\Middleware\AuthenticateShopify;
use AmadulHaque\LaravelShopify\Http\Middleware\EnsureActiveSubscription;
use AmadulHaque\LaravelShopify\Http\Middleware\VerifyShopifyOAuth;
use AmadulHaque\LaravelShopify\Http\Middleware\VerifyShopifyWebhook;
use AmadulHaque\LaravelShopify\Models\Store;
use AmadulHaque\LaravelShopify\Repositories\EloquentStoreRepository;
use AmadulHaque\LaravelShopify\Services\ShopifyClientFactory;
use AmadulHaque\LaravelShopify\Shopify;
use AmadulHaque\LaravelShopify\Support\CurrentStore;
use AmadulHaque\LaravelShopify\Support\Nonce;
use AmadulHaque\LaravelShopify\Support\RequestStoreResolver;
use AmadulHaque\LaravelShopify\Webhooks\ConfigWebhookRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class ShopifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/shopify.php', 'shopify');

        $this->app->singleton('shopify', fn (Application $app): Shopify => $app->make(Shopify::class));

        $this->app->singleton(ClientFactory::class, function (Application $app): ShopifyClientFactory {
            $channel = config('shopify.logging.channel');

            return new ShopifyClientFactory(
                $app->make(Factory::class),
                $app->make(Repository::class),
                $channel !== null ? Log::channel($channel) : Log::getFacadeRoot(),
            );
        });

        $this->app->bind(
            ShopifyApiClient::class,
            fn (Application $app): ShopifyApiClient => $app->make(ClientFactory::class)->default(),
        );

        $this->app->bind(StoreRepository::class, fn (): EloquentStoreRepository => new EloquentStoreRepository(
            config('shopify.store.model', Store::class),
        ));

        $this->app->bind(Nonce::class, fn (Application $app): Nonce => new Nonce(
            $app->make(\Illuminate\Contracts\Cache\Repository::class),
            (string) config('shopify.oauth.cache_prefix', 'shopify_oauth_nonce'),
            (int) config('shopify.oauth.nonce_ttl', 600),
        ));

        $this->app->singleton(WebhookRegistry::class, fn (): ConfigWebhookRegistry => new ConfigWebhookRegistry(
            (array) config('shopify.webhooks.topics', []),
        ));

        $this->app->bind(StoreResolver::class, RequestStoreResolver::class);
        $this->app->singleton(CurrentStore::class);
        $this->app->singleton(BillingManager::class, ShopifyBillingManager::class);
    }

    public function boot(Router $router, Dispatcher $events): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $router->aliasMiddleware('shopify.auth', AuthenticateShopify::class);
        $router->aliasMiddleware('shopify.oauth', VerifyShopifyOAuth::class);
        $router->aliasMiddleware('shopify.webhook', VerifyShopifyWebhook::class);
        $router->aliasMiddleware('shopify.billing', EnsureActiveSubscription::class);

        if (config('shopify.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/shopify.php');
        }

        if (config('shopify.billing.enabled', false) && config('shopify.billing.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/billing.php');
        }

        if (config('shopify.webhooks.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/webhooks.php');

            if (config('shopify.webhooks.auto_register', false)) {
                $events->listen(StoreInstalled::class, function (StoreInstalled $event): void {
                    $this->app->make(RegisterWebhooks::class)($event->store);
                });
            }
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/shopify.php' => config_path('shopify.php'),
            ], 'shopify-config');

            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ], 'shopify-migrations');

            $this->publishes([
                __DIR__.'/../Models/Store.php' => app_path('Models/ShopifyStore.php'),
            ], 'shopify-models');

            $this->commands([
                InstallCommand::class,
                RegisterWebhooksCommand::class,
                BillingSetupCommand::class,
            ]);
        }
    }
}
