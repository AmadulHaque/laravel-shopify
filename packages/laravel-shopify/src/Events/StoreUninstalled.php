<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a store is uninstalled (via the app/uninstalled webhook).
 *
 * Defined now; dispatched by the Phase 4 lifecycle/webhook handling.
 */
final class StoreUninstalled
{
    use Dispatchable;

    public function __construct(public readonly string $shopDomain) {}
}
