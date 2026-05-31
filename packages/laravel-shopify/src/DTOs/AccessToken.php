<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use AmadulHaque\LaravelShopify\Enums\TokenType;
use AmadulHaque\LaravelShopify\ValueObjects\Scopes;

/**
 * The result of an OAuth token exchange.
 */
final readonly class AccessToken
{
    public function __construct(
        public string $token,
        public Scopes $scopes,
        public TokenType $type = TokenType::Offline,
    ) {}

    /**
     * Build from the JSON body returned by `POST /admin/oauth/access_token`.
     *
     * @param  array<string, mixed>  $json
     */
    public static function fromResponse(array $json): self
    {
        return new self(
            token: (string) ($json['access_token'] ?? ''),
            scopes: Scopes::fromString((string) ($json['scope'] ?? '')),
            type: isset($json['expires_in']) ? TokenType::Online : TokenType::Offline,
        );
    }
}
