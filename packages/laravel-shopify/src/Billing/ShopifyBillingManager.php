<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Billing;

use AmadulHaque\LaravelShopify\Actions\CreateSubscription;
use AmadulHaque\LaravelShopify\Actions\FetchActiveSubscription;
use AmadulHaque\LaravelShopify\Actions\RecordUsage;
use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\DTOs\Subscription;
use AmadulHaque\LaravelShopify\DTOs\SubscriptionConfirmation;
use AmadulHaque\LaravelShopify\DTOs\UsageRecord;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;
use AmadulHaque\LaravelShopify\Exceptions\ShopifyApiException;
use AmadulHaque\LaravelShopify\Exceptions\SubscriptionRequiredException;
use AmadulHaque\LaravelShopify\Support\CurrentStore;

/**
 * Orchestrates Shopify billing: subscribe, enforce, record usage, cancel.
 *
 * Enforcement checks ({@see self::hasActivePlan()}) read the persisted store
 * columns for speed; those columns are kept fresh by the billing confirmation
 * controller and the `app_subscriptions/update` webhook.
 */
final readonly class ShopifyBillingManager implements BillingManager
{
    public function __construct(
        private CreateSubscription $createSubscription,
        private FetchActiveSubscription $fetchActiveSubscription,
        private RecordUsage $recordUsage,
        private PlanRepository $plans,
        private StoreRepository $stores,
        private ClientFactory $clients,
        private CurrentStore $currentStore,
    ) {}

    public function subscribe(ShopModel $store, ?string $plan = null): SubscriptionConfirmation
    {
        return ($this->createSubscription)($store, $this->plans->resolve($plan));
    }

    public function activeSubscription(ShopModel $store): ?Subscription
    {
        return ($this->fetchActiveSubscription)($store);
    }

    public function hasActivePlan(ShopModel $store, ?string $plan = null): bool
    {
        if (! $store->isSubscribed()) {
            return false;
        }

        return $plan === null || $store->getPlan() === $plan;
    }

    public function requirePlan(?string $plan = null): Subscription
    {
        $store = $this->currentStore->get()
            ?? throw new ShopifyApiException('Unable to resolve the current store for billing enforcement.');

        if ($this->hasActivePlan($store, $plan)) {
            $subscription = $this->activeSubscription($store);

            if ($subscription !== null && $subscription->isActive()) {
                return $subscription;
            }
        }

        $confirmation = $this->subscribe($store, $plan);

        throw new SubscriptionRequiredException(
            $confirmation->confirmationUrl,
            $plan ?? $this->plans->default()->name,
        );
    }

    public function recordUsage(ShopModel $store, string $description, float $amount): UsageRecord
    {
        $subscription = $this->activeSubscription($store);

        if ($subscription?->usageLineItemId === null) {
            throw BillingException::noUsageLineItem($store->getShopDomain());
        }

        $plan = $this->plans->resolve($store->getPlan());

        return ($this->recordUsage)(
            $store,
            $subscription->usageLineItemId,
            $plan->currency,
            $description,
            $amount,
        );
    }

    public function cancel(ShopModel $store): void
    {
        $subscriptionId = $store->getSubscriptionId();

        if ($subscriptionId !== null) {
            $this->clients->make($store)->graphql(
                <<<'GRAPHQL'
                mutation appSubscriptionCancel($id: ID!) {
                    appSubscriptionCancel(id: $id) {
                        appSubscription { id status }
                        userErrors { field message }
                    }
                }
                GRAPHQL,
                ['id' => $subscriptionId],
            );
        }

        $this->stores->clearSubscription($store->getShopDomain());
    }
}
