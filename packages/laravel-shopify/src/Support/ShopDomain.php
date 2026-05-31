<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

/**
 * Validation and normalization for `*.myshopify.com` shop domains.
 *
 * Strict validation here is a security control: the domain is interpolated into
 * outbound API/OAuth URLs, so anything that isn't a genuine myshopify domain is
 * rejected to prevent SSRF and open-redirect attacks.
 */
final class ShopDomain
{
    private const PATTERN = '/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/';

    /**
     * Normalize user input (scheme, trailing slash, casing, bare handle) to a
     * canonical `handle.myshopify.com` domain.
     */
    public static function normalize(string $shop): string
    {
        $shop = strtolower(trim($shop));
        $shop = (string) preg_replace('#^https?://#', '', $shop);
        $shop = rtrim($shop, '/');
        $shop = explode('/', $shop)[0];

        if ($shop !== '' && ! str_contains($shop, '.')) {
            $shop .= '.myshopify.com';
        }

        return $shop;
    }

    public static function isValid(string $shop): bool
    {
        return preg_match(self::PATTERN, self::normalize($shop)) === 1;
    }
}
