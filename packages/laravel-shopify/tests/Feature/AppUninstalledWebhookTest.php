<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Events\StoreUninstalled;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Event;

it('deactivates the store and fires StoreUninstalled on app/uninstalled', function (): void {
    Event::fake([StoreUninstalled::class]);

    $store = Store::factory()->create([
        'shop_domain' => 'demo.myshopify.com',
        'access_token' => 'shpat_live',
    ]);

    postWebhook('app/uninstalled', ['shop' => 'demo.myshopify.com'])->assertOk();

    $store->refresh();

    expect($store->uninstalled_at)->not->toBeNull()
        ->and($store->access_token)->toBeNull()
        ->and($store->isActive())->toBeFalse();

    Event::assertDispatched(StoreUninstalled::class, fn (StoreUninstalled $e) => $e->shopDomain === 'demo.myshopify.com');
});
