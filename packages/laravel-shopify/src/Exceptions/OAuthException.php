<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

/**
 * Raised when the OAuth install/callback flow fails verification or token exchange.
 */
class OAuthException extends ShopifyException
{
    public static function invalidShopDomain(string $shop): self
    {
        return new self("The shop domain [{$shop}] is not a valid *.myshopify.com domain.");
    }

    public static function invalidHmac(): self
    {
        return new self('The OAuth request failed HMAC verification.');
    }

    public static function invalidState(): self
    {
        return new self('The OAuth request state (nonce) is missing, expired, or does not match.');
    }

    public static function tokenExchangeFailed(string $shop, int $status): self
    {
        return new self("Failed to exchange the OAuth code for an access token for [{$shop}] (status {$status}).");
    }
}
