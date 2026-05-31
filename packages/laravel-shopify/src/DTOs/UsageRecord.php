<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

/**
 * A recorded usage charge against a subscription's usage line item.
 */
final readonly class UsageRecord
{
    public function __construct(
        public string $id,
        public string $description,
        public float $amount,
    ) {}
}
