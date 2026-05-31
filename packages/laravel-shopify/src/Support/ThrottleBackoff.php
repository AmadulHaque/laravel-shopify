<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

use AmadulHaque\LaravelShopify\DTOs\ThrottleStatus;

/**
 * Computes how long to wait before retrying a throttled request.
 *
 * When the GraphQL cost bucket is known, the precise restore time is preferred;
 * otherwise an exponential backoff with full jitter is used. The two strategies
 * are combined by taking the larger delay, then clamped to the configured max.
 */
final class ThrottleBackoff
{
    /**
     * @param  int  $attempt  Zero-based retry attempt number.
     * @param  int  $baseMs  Exponential backoff base in milliseconds.
     * @param  int  $maxMs  Hard ceiling for the delay in milliseconds.
     * @param  int|null  $jitterSeed  Deterministic seed for jitter (testing); random when null.
     */
    public static function delayMs(
        int $attempt,
        int $baseMs,
        int $maxMs,
        ?ThrottleStatus $status = null,
        ?int $requestedCost = null,
        ?int $jitterSeed = null,
    ): int {
        $exponential = $baseMs * (2 ** $attempt);
        $jittered = self::applyJitter($exponential, $jitterSeed);

        $delay = $jittered;

        if ($status instanceof ThrottleStatus && $requestedCost !== null) {
            $restoreMs = (int) ceil($status->secondsToRestore($requestedCost) * 1000);
            $delay = max($delay, $restoreMs);
        }

        return (int) min($delay, $maxMs);
    }

    private static function applyJitter(int $value, ?int $seed): int
    {
        if ($value <= 0) {
            return 0;
        }

        if ($seed !== null) {
            // Deterministic "half + scaled remainder" jitter for reproducible tests.
            $fraction = ($seed % 100) / 100;

            return (int) (($value / 2) + ($value / 2) * $fraction);
        }

        return random_int((int) ($value / 2), $value);
    }
}
