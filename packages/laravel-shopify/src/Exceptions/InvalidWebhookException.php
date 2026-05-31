<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

/**
 * Raised when an incoming webhook fails HMAC verification.
 *
 * Used by the Phase 2 webhook system; defined now so the verification helper
 * and middleware have a stable exception to throw.
 */
class InvalidWebhookException extends ShopifyException
{
    public static function invalidHmac(): self
    {
        return new self('The webhook payload failed HMAC verification.');
    }
}
