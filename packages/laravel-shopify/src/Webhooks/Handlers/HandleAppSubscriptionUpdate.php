<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Webhooks\Handlers;

use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Contracts\WebhookHandler;
use AmadulHaque\LaravelShopify\DTOs\Subscription;
use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;
use AmadulHaque\LaravelShopify\Events\SubscriptionCancelled;

/**
 * Built-in handler for the `app_subscriptions/update` topic.
 *
 * Keeps the store's persisted subscription state in sync with Shopify so the
 * (no-API-call) enforcement checks stay accurate.
 */
final readonly class HandleAppSubscriptionUpdate implements WebhookHandler
{
    public function __construct(private StoreRepository $stores) {}

    public function handle(WebhookPayload $payload): void
    {
        $data = $payload->payload['app_subscription'] ?? [];

        $status = SubscriptionStatus::tryFromName($data['status'] ?? null);

        if ($status === null) {
            return;
        }

        if ($status->isActive()) {
            $this->stores->saveSubscription(
                $payload->shopDomain,
                (string) ($data['name'] ?? ''),
                new Subscription(
                    id: (string) ($data['admin_graphql_api_id'] ?? ''),
                    name: (string) ($data['name'] ?? ''),
                    status: $status,
                    test: (bool) ($data['test'] ?? false),
                    trialDays: null,
                    currentPeriodEnd: null,
                    recurringLineItemId: null,
                    usageLineItemId: null,
                ),
            );

            return;
        }

        $this->stores->clearSubscription($payload->shopDomain);

        SubscriptionCancelled::dispatch($payload->shopDomain);
    }
}
