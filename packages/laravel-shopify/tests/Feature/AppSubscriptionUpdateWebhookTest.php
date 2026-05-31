<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;
use AmadulHaque\LaravelShopify\Events\SubscriptionCancelled;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Event;

it('clears the subscription when the app_subscriptions/update webhook reports cancellation', function (): void {
    Event::fake([SubscriptionCancelled::class]);

    $store = Store::factory()->subscribed('pro')->create([
        'shop_domain' => 'demo.myshopify.com',
        'access_token' => 'shpat_x',
    ]);

    postWebhook('app_subscriptions/update', [
        'app_subscription' => [
            'admin_graphql_api_id' => 'gid://shopify/AppSubscription/1',
            'name' => 'pro',
            'status' => 'CANCELLED',
        ],
    ])->assertOk();

    $store->refresh();

    expect($store->subscription_status)->toBeNull()
        ->and($store->plan)->toBeNull()
        ->and($store->isSubscribed())->toBeFalse();

    Event::assertDispatched(SubscriptionCancelled::class);
});

it('syncs an active subscription status from the webhook', function (): void {
    $store = Store::factory()->create([
        'shop_domain' => 'demo.myshopify.com',
        'access_token' => 'shpat_x',
    ]);

    postWebhook('app_subscriptions/update', [
        'app_subscription' => [
            'admin_graphql_api_id' => 'gid://shopify/AppSubscription/2',
            'name' => 'pro',
            'status' => 'ACTIVE',
        ],
    ])->assertOk();

    $store->refresh();

    expect($store->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($store->plan)->toBe('pro')
        ->and($store->subscription_id)->toBe('gid://shopify/AppSubscription/2');
});
