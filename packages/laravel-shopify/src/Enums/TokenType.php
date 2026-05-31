<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Enums;

/**
 * The Shopify OAuth access-token grant types.
 *
 * Offline tokens are long-lived, per-shop credentials used for background work.
 * Online tokens are user-scoped and short-lived; reserved for future per-user actions.
 */
enum TokenType: string
{
    case Offline = 'offline';
    case Online = 'online';
}
