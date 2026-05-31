<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a store's subscription is cancelled.
 */
final class SubscriptionCancelled
{
    use Dispatchable;

    public function __construct(public readonly string $shopDomain) {}
}
