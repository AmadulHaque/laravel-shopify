<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->store = Store::factory()->create([
        'shop_domain' => 'demo.myshopify.com',
        'access_token' => 'shpat_token',
    ]);
});

it('creates a subscription with recurring and usage line items', function (): void {
    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['appSubscriptionCreate' => [
            'appSubscription' => ['id' => 'gid://shopify/AppSubscription/1', 'status' => 'PENDING'],
            'confirmationUrl' => 'https://demo.myshopify.com/admin/charges/1/confirm',
            'userErrors' => [],
        ]]]),
    ]);

    $confirmation = app(BillingManager::class)->subscribe($this->store, 'pro');

    expect($confirmation->confirmationUrl)->toBe('https://demo.myshopify.com/admin/charges/1/confirm')
        ->and($confirmation->subscriptionId)->toBe('gid://shopify/AppSubscription/1');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return data_get($data, 'variables.name') === 'pro'
            && data_get($data, 'variables.test') === true
            && data_get($data, 'variables.trialDays') === 7
            && data_get($data, 'variables.returnUrl') === 'https://app.test/shopify/billing/confirm?shop=demo.myshopify.com'
            && count((array) data_get($data, 'variables.lineItems')) === 2
            && data_get($data, 'variables.lineItems.0.plan.appRecurringPricingDetails.price.amount') === 19.99
            && data_get($data, 'variables.lineItems.0.plan.appRecurringPricingDetails.interval') === 'EVERY_30_DAYS'
            && data_get($data, 'variables.lineItems.1.plan.appUsagePricingDetails.cappedAmount.amount') === 50.0;
    });
});

it('throws a BillingException on userErrors', function (): void {
    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['appSubscriptionCreate' => [
            'appSubscription' => null,
            'confirmationUrl' => null,
            'userErrors' => [['field' => ['name'], 'message' => 'Plan already active']],
        ]]]),
    ]);

    expect(fn () => app(BillingManager::class)->subscribe($this->store, 'pro'))
        ->toThrow(BillingException::class, 'Plan already active');
});
