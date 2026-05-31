<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use Illuminate\Http\Request;

/**
 * The query parameters Shopify sends to the OAuth callback endpoint.
 */
final readonly class OAuthCallbackData
{
    public function __construct(
        public string $shop,
        public string $code,
        public string $state,
        public ?string $hmac,
        public ?string $timestamp,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            shop: (string) $request->query('shop', ''),
            code: (string) $request->query('code', ''),
            state: (string) $request->query('state', ''),
            hmac: $request->query('hmac') !== null ? (string) $request->query('hmac') : null,
            timestamp: $request->query('timestamp') !== null ? (string) $request->query('timestamp') : null,
        );
    }
}
