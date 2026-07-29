<?php

namespace Decoupled\Shopify\Support;

use DateTimeImmutable;

final readonly class AccessToken
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $value,
        public array $scopes = [],
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $refreshToken = null,
    ) {}
}
