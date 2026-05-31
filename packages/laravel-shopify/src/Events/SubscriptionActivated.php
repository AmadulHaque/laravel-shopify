<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\DTOs\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a store's subscription becomes active (after merchant approval).
 */
final class SubscriptionActivated
{
    use Dispatchable;

    public function __construct(
        public readonly ShopModel $store,
        public readonly Subscription $subscription,
    ) {}
}
