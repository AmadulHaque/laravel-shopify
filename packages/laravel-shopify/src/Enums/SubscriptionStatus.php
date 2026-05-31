<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Enums;

/**
 * The lifecycle states of a Shopify app subscription.
 *
 * Mirrors Shopify's `AppSubscriptionStatus` GraphQL enum.
 */
enum SubscriptionStatus: string
{
    case Pending = 'PENDING';
    case Active = 'ACTIVE';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Frozen = 'FROZEN';
    case Cancelled = 'CANCELLED';

    /**
     * Whether the subscription currently entitles the merchant to the plan.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public static function tryFromName(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper($value));
    }
}
