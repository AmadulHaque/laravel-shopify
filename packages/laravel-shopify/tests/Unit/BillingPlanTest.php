<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Billing\Plan;
use AmadulHaque\LaravelShopify\Billing\PlanRepository;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;

function planRepository(array $plans, string $default = 'pro'): PlanRepository
{
    config()->set('shopify.billing.plans', $plans);
    config()->set('shopify.billing.default_plan', $default);

    return new PlanRepository(config());
}

it('builds a plan with a usage component from config', function (): void {
    $plan = Plan::fromConfig('pro', [
        'price' => 19.99,
        'currency' => 'USD',
        'interval' => 'EVERY_30_DAYS',
        'trial_days' => 7,
        'usage' => ['capped_amount' => 50.0, 'terms' => '$0.10 per order'],
    ]);

    expect($plan->price)->toBe(19.99)
        ->and($plan->interval)->toBe('EVERY_30_DAYS')
        ->and($plan->trialDays)->toBe(7)
        ->and($plan->hasUsagePricing())->toBeTrue()
        ->and($plan->usage['capped_amount'])->toBe(50.0);
});

it('treats a plan without a usage key as recurring-only', function (): void {
    $plan = Plan::fromConfig('basic', ['price' => 9.99, 'interval' => 'EVERY_30_DAYS']);

    expect($plan->hasUsagePricing())->toBeFalse()
        ->and($plan->trialDays)->toBe(0)
        ->and($plan->currency)->toBe('USD');
});

it('resolves named and default plans, and throws for unknown ones', function (): void {
    $repo = planRepository([
        'pro' => ['price' => 19.99],
        'basic' => ['price' => 9.99],
    ]);

    expect($repo->find('basic')->price)->toBe(9.99)
        ->and($repo->default()->name)->toBe('pro')
        ->and($repo->resolve(null)->name)->toBe('pro')
        ->and(array_keys($repo->all()))->toBe(['pro', 'basic']);

    expect(fn () => $repo->find('enterprise'))->toThrow(BillingException::class);
});
