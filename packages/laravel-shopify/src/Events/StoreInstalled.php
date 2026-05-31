<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a store completes OAuth and its token is persisted.
 */
final class StoreInstalled
{
    use Dispatchable;

    public function __construct(public readonly ShopModel $store) {}
}
