<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Exceptions\SubscriptionRequiredException;
use AmadulHaque\LaravelShopify\Facades\Shopify;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

function fakeActiveSubscription(string $name = 'pro'): void
{
    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [[
            'id' => 'gid://shopify/AppSubscription/1',
            'name' => $name,
            'status' => 'ACTIVE',
            'test' => true,
            'trialDays' => 0,
            'lineItems' => [],
        ]]]]]),
    ]);
}

it('requirePlan throws a redirect-rendering exception when unsubscribed', function (): void {
    $store = Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);
    Shopify::resolveStoreUsing(fn () => $store);

    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['appSubscriptionCreate' => [
            'appSubscription' => ['id' => 'gid://sub/1', 'status' => 'PENDING'],
            'confirmationUrl' => 'https://demo.myshopify.com/confirm/1',
            'userErrors' => [],
        ]]]),
    ]);

    try {
        app(BillingManager::class)->requirePlan('pro');
        $this->fail('Expected SubscriptionRequiredException.');
    } catch (SubscriptionRequiredException $e) {
        expect($e->confirmationUrl)->toBe('https://demo.myshopify.com/confirm/1')
            ->and($e->render(request())->getTargetUrl())->toBe('https://demo.myshopify.com/confirm/1');
    }
});

it('requirePlan returns the active subscription when subscribed', function (): void {
    $store = Store::factory()->subscribed('pro')->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);
    Shopify::resolveStoreUsing(fn () => $store);

    fakeActiveSubscription();

    $subscription = app(BillingManager::class)->requirePlan('pro');

    expect($subscription->isActive())->toBeTrue()
        ->and($subscription->name)->toBe('pro');
});

it('middleware redirects an unsubscribed store to the confirmation page', function (): void {
    Route::middleware('shopify.billing:pro')->get('/billing-protected', fn () => 'ok');

    Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['appSubscriptionCreate' => [
            'appSubscription' => ['id' => 'gid://sub/1', 'status' => 'PENDING'],
            'confirmationUrl' => 'https://demo.myshopify.com/confirm/9',
            'userErrors' => [],
        ]]]),
    ]);

    $this->get('/billing-protected?shop=demo.myshopify.com')
        ->assertRedirect('https://demo.myshopify.com/confirm/9');
});

it('middleware passes a subscribed store through without an API call', function (): void {
    Route::middleware('shopify.billing:pro')->get('/billing-protected', fn () => 'ok');

    Store::factory()->subscribed('pro')->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake();

    $this->get('/billing-protected?shop=demo.myshopify.com')->assertOk()->assertSee('ok');

    Http::assertNothingSent();
});

it('middleware 403s when no store can be resolved', function (): void {
    Route::middleware('shopify.billing')->get('/billing-protected', fn () => 'ok');

    $this->get('/billing-protected')->assertForbidden();
});

it('honors a custom store resolver override', function (): void {
    Route::middleware('shopify.billing')->get('/billing-protected', fn () => 'ok');

    $store = Store::factory()->subscribed()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);
    Shopify::resolveStoreUsing(fn () => $store);

    Http::fake();

    $this->get('/billing-protected')->assertOk()->assertSee('ok');
});
