<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;
use AmadulHaque\LaravelShopify\Events\SubscriptionActivated;
use AmadulHaque\LaravelShopify\Events\SubscriptionDeclined;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('activates and persists the subscription on a successful return', function (): void {
    Event::fake([SubscriptionActivated::class]);

    $store = Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [[
            'id' => 'gid://shopify/AppSubscription/1',
            'name' => 'pro',
            'status' => 'ACTIVE',
            'test' => true,
            'trialDays' => 7,
            'lineItems' => [],
        ]]]]]),
    ]);

    $this->get('/shopify/billing/confirm?shop=demo.myshopify.com&charge_id=1')
        ->assertRedirect('/?billing=activated');

    $store->refresh();

    expect($store->subscription_status)->toBe(SubscriptionStatus::Active)
        ->and($store->plan)->toBe('pro')
        ->and($store->subscription_id)->toBe('gid://shopify/AppSubscription/1')
        ->and($store->plan_activated_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionActivated::class);
});

it('treats a missing active subscription as declined', function (): void {
    Event::fake([SubscriptionDeclined::class]);

    Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => []]]]),
    ]);

    $this->get('/shopify/billing/confirm?shop=demo.myshopify.com')
        ->assertRedirect('/?billing=declined');

    Event::assertDispatched(SubscriptionDeclined::class);
});

it('rejects an unknown shop on the confirm route', function (): void {
    $this->get('/shopify/billing/confirm?shop=unknown.myshopify.com')->assertForbidden();
});
