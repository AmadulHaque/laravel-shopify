<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Tests;

use AmadulHaque\LaravelShopify\Providers\ShopifyServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ShopifyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), function (Repository $config): void {
            $config->set('shopify.api_key', 'test-api-key');
            $config->set('shopify.api_secret', 'test-api-secret');
            $config->set('shopify.scopes', 'read_products,write_products');
            $config->set('shopify.app_url', 'https://app.test');

            $config->set('shopify.billing.enabled', true);

            $config->set('database.default', 'testing');
        });
    }
}
