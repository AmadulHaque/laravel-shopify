<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\DTOs\Subscription;
use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;

it('maps a subscription node and resolves line items by pricing type', function (): void {
    $subscription = Subscription::fromGraphQl([
        'id' => 'gid://shopify/AppSubscription/1',
        'name' => 'pro',
        'status' => 'ACTIVE',
        'test' => true,
        'trialDays' => 7,
        'currentPeriodEnd' => '2026-07-01T00:00:00Z',
        'lineItems' => [
            ['id' => 'gid://line/recurring', 'plan' => ['pricingDetails' => ['__typename' => 'AppRecurringPricing']]],
            ['id' => 'gid://line/usage', 'plan' => ['pricingDetails' => ['__typename' => 'AppUsagePricing']]],
        ],
    ]);

    expect($subscription->id)->toBe('gid://shopify/AppSubscription/1')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->isActive())->toBeTrue()
        ->and($subscription->test)->toBeTrue()
        ->and($subscription->trialDays)->toBe(7)
        ->and($subscription->recurringLineItemId)->toBe('gid://line/recurring')
        ->and($subscription->usageLineItemId)->toBe('gid://line/usage');
});

it('defaults to pending for an unknown status and null line items', function (): void {
    $subscription = Subscription::fromGraphQl(['id' => 'x', 'name' => 'basic', 'status' => 'WAT']);

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->isActive())->toBeFalse()
        ->and($subscription->usageLineItemId)->toBeNull();
});
