<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

/**
 * The GraphQL query-cost throttle bucket, parsed from `extensions.cost`.
 *
 * Shopify's GraphQL Admin API uses a calculated-query-cost leaky bucket. Each
 * response reports how much of the bucket remains and the rate it restores at,
 * which lets us pre-emptively back off before hitting a THROTTLED error.
 *
 * @see https://shopify.dev/docs/api/usage/rate-limits
 */
final readonly class ThrottleStatus
{
    public function __construct(
        public float $maximumAvailable,
        public float $currentlyAvailable,
        public float $restoreRate,
        public int $requestedQueryCost,
        public int $actualQueryCost,
    ) {}

    /**
     * Build from a decoded GraphQL response's `extensions` array.
     *
     * @param  array<string, mixed>  $extensions
     */
    public static function fromExtensions(array $extensions): ?self
    {
        $cost = $extensions['cost'] ?? null;

        if (! is_array($cost) || ! isset($cost['throttleStatus'])) {
            return null;
        }

        $throttle = $cost['throttleStatus'];

        return new self(
            maximumAvailable: (float) ($throttle['maximumAvailable'] ?? 0),
            currentlyAvailable: (float) ($throttle['currentlyAvailable'] ?? 0),
            restoreRate: (float) ($throttle['restoreRate'] ?? 0),
            requestedQueryCost: (int) ($cost['requestedQueryCost'] ?? 0),
            actualQueryCost: (int) ($cost['actualQueryCost'] ?? 0),
        );
    }

    /**
     * Whether the remaining bucket is below the configured safety buffer.
     */
    public function needsBackoff(int $buffer): bool
    {
        return $this->currentlyAvailable < $buffer;
    }

    /**
     * Seconds required for the bucket to restore enough points for `$needed` cost.
     */
    public function secondsToRestore(int $needed): float
    {
        if ($this->restoreRate <= 0.0) {
            return 0.0;
        }

        $deficit = $needed - $this->currentlyAvailable;

        if ($deficit <= 0.0) {
            return 0.0;
        }

        return $deficit / $this->restoreRate;
    }
}
