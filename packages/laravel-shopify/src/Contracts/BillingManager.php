<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\DTOs\Subscription;
use AmadulHaque\LaravelShopify\DTOs\SubscriptionConfirmation;
use AmadulHaque\LaravelShopify\DTOs\UsageRecord;
use AmadulHaque\LaravelShopify\Exceptions\SubscriptionRequiredException;

/**
 * Manages Shopify billing (recurring + usage charges) via the GraphQL Billing API.
 */
interface BillingManager
{
    /**
     * Create a subscription for the store; returns the URL the merchant must approve.
     */
    public function subscribe(ShopModel $store, ?string $plan = null): SubscriptionConfirmation;

    /**
     * The store's current active subscription as reported by Shopify, or null.
     */
    public function activeSubscription(ShopModel $store): ?Subscription;

    /**
     * Whether the store has an active subscription (optionally to a specific plan).
     * Reads persisted state — no API call.
     */
    public function hasActivePlan(ShopModel $store, ?string $plan = null): bool;

    /**
     * Ensure the current request's store has an active plan, or throw a
     * {@see SubscriptionRequiredException}
     * carrying the confirmation URL to redirect to.
     */
    public function requirePlan(?string $plan = null): Subscription;

    /**
     * Record a usage charge against the store's usage line item.
     */
    public function recordUsage(ShopModel $store, string $description, float $amount): UsageRecord;

    /**
     * Cancel the store's active subscription.
     */
    public function cancel(ShopModel $store): void;
}
