<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Webhooks\ConfigWebhookRegistry;
use AmadulHaque\LaravelShopify\Webhooks\Handlers\HandleAppUninstalled;

it('seeds handlers from config and normalizes topic spelling', function (): void {
    $registry = new ConfigWebhookRegistry([
        'app/uninstalled' => HandleAppUninstalled::class,
    ]);

    // Lookups work regardless of which spelling the caller uses.
    expect($registry->handlerFor('app/uninstalled'))->toBe(HandleAppUninstalled::class)
        ->and($registry->handlerFor('APP_UNINSTALLED'))->toBe(HandleAppUninstalled::class)
        ->and($registry->topics())->toBe(['APP_UNINSTALLED']);
});

it('registers handlers at runtime and returns null for unknown topics', function (): void {
    $registry = new ConfigWebhookRegistry;

    expect($registry->handlerFor('orders/create'))->toBeNull();

    $registry->register('orders/create', HandleAppUninstalled::class);

    expect($registry->handlerFor('orders/create'))->toBe(HandleAppUninstalled::class)
        ->and($registry->handlers())->toBe(['ORDERS_CREATE' => HandleAppUninstalled::class]);
});
