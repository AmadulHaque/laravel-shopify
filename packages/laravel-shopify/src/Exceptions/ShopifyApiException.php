<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

use Throwable;

/**
 * Raised when an Admin API request fails at the transport level.
 */
class ShopifyApiException extends ShopifyException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function missingStore(): self
    {
        return new self('No store is bound to the Shopify API client. Call forStore() first.');
    }

    public static function missingAccessToken(string $domain): self
    {
        return new self("Store [{$domain}] has no access token; re-authentication is required.");
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function requestFailed(string $domain, int $status, array $body): self
    {
        return new self("Shopify API request for [{$domain}] failed with status {$status}.", $status, $body);
    }
}
