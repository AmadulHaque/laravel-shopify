<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

/**
 * The result of creating a subscription: the merchant must visit the
 * confirmation URL to approve the charge before it becomes active.
 */
final readonly class SubscriptionConfirmation
{
    public function __construct(
        public string $confirmationUrl,
        public ?string $subscriptionId,
    ) {}
}
