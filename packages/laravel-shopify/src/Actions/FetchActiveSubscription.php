<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\DTOs\Subscription;

/**
 * Fetches the store's current active subscription (source of truth) from Shopify.
 *
 * @see https://shopify.dev/docs/api/admin-graphql/latest/objects/AppInstallation
 */
final readonly class FetchActiveSubscription
{
    private const QUERY = <<<'GRAPHQL'
    query activeSubscriptions {
        currentAppInstallation {
            activeSubscriptions {
                id
                name
                status
                test
                trialDays
                currentPeriodEnd
                lineItems {
                    id
                    plan {
                        pricingDetails {
                            __typename
                        }
                    }
                }
            }
        }
    }
    GRAPHQL;

    public function __construct(private ClientFactory $clients) {}

    public function __invoke(ShopModel $store): ?Subscription
    {
        $response = $this->clients->make($store)->graphql(self::QUERY);

        $subscriptions = $response->data['currentAppInstallation']['activeSubscriptions'] ?? [];

        if (! is_array($subscriptions) || $subscriptions === []) {
            return null;
        }

        return Subscription::fromGraphQl($subscriptions[0]);
    }
}
