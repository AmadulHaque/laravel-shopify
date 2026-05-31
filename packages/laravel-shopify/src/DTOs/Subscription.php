<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;

/**
 * A Shopify app subscription, decoded from the GraphQL Billing API.
 */
final readonly class Subscription
{
    public function __construct(
        public string $id,
        public string $name,
        public SubscriptionStatus $status,
        public bool $test,
        public ?int $trialDays,
        public ?string $currentPeriodEnd,
        public ?string $recurringLineItemId,
        public ?string $usageLineItemId,
    ) {}

    /**
     * Build from a single `activeSubscriptions` / `appSubscription` node.
     *
     * @param  array<string, mixed>  $node
     */
    public static function fromGraphQl(array $node): self
    {
        [$recurringLineItemId, $usageLineItemId] = self::resolveLineItems($node['lineItems'] ?? []);

        return new self(
            id: (string) ($node['id'] ?? ''),
            name: (string) ($node['name'] ?? ''),
            status: SubscriptionStatus::tryFromName($node['status'] ?? null) ?? SubscriptionStatus::Pending,
            test: (bool) ($node['test'] ?? false),
            trialDays: isset($node['trialDays']) ? (int) $node['trialDays'] : null,
            currentPeriodEnd: $node['currentPeriodEnd'] ?? null,
            recurringLineItemId: $recurringLineItemId,
            usageLineItemId: $usageLineItemId,
        );
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Resolve the recurring and usage line-item ids by their pricing type.
     *
     * @param  list<array<string, mixed>>  $lineItems
     * @return array{0: ?string, 1: ?string}
     */
    private static function resolveLineItems(array $lineItems): array
    {
        $recurring = null;
        $usage = null;

        foreach ($lineItems as $lineItem) {
            $type = $lineItem['plan']['pricingDetails']['__typename'] ?? null;
            $id = $lineItem['id'] ?? null;

            if ($type === 'AppUsagePricing') {
                $usage = $id;
            } elseif ($type === 'AppRecurringPricing') {
                $recurring = $id;
            }
        }

        return [$recurring, $usage];
    }
}
