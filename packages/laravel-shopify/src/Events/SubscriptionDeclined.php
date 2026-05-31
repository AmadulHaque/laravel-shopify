<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a merchant returns from the confirmation page without an active subscription.
 */
final class SubscriptionDeclined
{
    use Dispatchable;

    public function __construct(public readonly ShopModel $store) {}
}
