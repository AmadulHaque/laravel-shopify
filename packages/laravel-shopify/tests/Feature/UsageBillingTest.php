<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Http;

it('records a usage charge against the usage line item', function (): void {
    $store = Store::factory()->subscribed('pro')->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake([
        '*/graphql.json' => Http::sequence()
            ->push(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [[
                'id' => 'gid://shopify/AppSubscription/1',
                'name' => 'pro',
                'status' => 'ACTIVE',
                'test' => true,
                'trialDays' => 0,
                'lineItems' => [
                    ['id' => 'gid://line/usage', 'plan' => ['pricingDetails' => ['__typename' => 'AppUsagePricing']]],
                ],
            ]]]]])
            ->push(['data' => ['appUsageRecordCreate' => [
                'appUsageRecord' => ['id' => 'gid://shopify/AppUsageRecord/7'],
                'userErrors' => [],
            ]]]),
    ]);

    $record = app(BillingManager::class)->recordUsage($store, 'Extra order processed', 0.10);

    expect($record->id)->toBe('gid://shopify/AppUsageRecord/7')
        ->and($record->amount)->toBe(0.10)
        ->and($record->description)->toBe('Extra order processed');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return data_get($data, 'variables.subscriptionLineItemId') === 'gid://line/usage'
            && data_get($data, 'variables.price.amount') === 0.10
            && data_get($data, 'variables.price.currencyCode') === 'USD';
    });
});

it('throws when the store has no usage line item', function (): void {
    $store = Store::factory()->subscribed('pro')->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    Http::fake([
        '*/graphql.json' => Http::response(['data' => ['currentAppInstallation' => ['activeSubscriptions' => [[
            'id' => 'gid://shopify/AppSubscription/1',
            'name' => 'pro',
            'status' => 'ACTIVE',
            'lineItems' => [
                ['id' => 'gid://line/recurring', 'plan' => ['pricingDetails' => ['__typename' => 'AppRecurringPricing']]],
            ],
        ]]]]]),
    ]);

    expect(fn () => app(BillingManager::class)->recordUsage($store, 'Extra', 1.0))
        ->toThrow(BillingException::class);
});
