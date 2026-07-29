<?php

namespace Decoupled\Shopify\Support;

use InvalidArgumentException;

final readonly class Shop
{
    public function __construct(public string $domain)
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*\\.myshopify\\.com$/', $domain)) {
            throw new InvalidArgumentException('Shop domain must be a valid *.myshopify.com domain.');
        }
    }

    public static function from(string|self $shop): self
    {
        return $shop instanceof self ? $shop : new self(strtolower(trim($shop)));
    }
}
