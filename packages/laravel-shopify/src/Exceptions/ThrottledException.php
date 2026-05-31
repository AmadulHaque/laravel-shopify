<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

use AmadulHaque\LaravelShopify\DTOs\ThrottleStatus;

/**
 * Raised when the Admin API keeps throttling a request after all retries are exhausted.
 */
class ThrottledException extends ShopifyApiException
{
    public static function exhausted(string $domain, int $attempts, ?ThrottleStatus $status = null): self
    {
        $exception = new self(
            "Shopify throttled requests for [{$domain}] after {$attempts} attempts.",
            429,
        );

        $exception->throttleStatus = $status;

        return $exception;
    }

    public ?ThrottleStatus $throttleStatus = null;
}
