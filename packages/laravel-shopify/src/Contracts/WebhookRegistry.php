<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

/**
 * Maps Shopify webhook topics to the handlers that process them.
 *
 * Interface-only extension point for the Phase 2 webhook system; the concrete
 * implementation, controller, and queue jobs are added in that phase.
 */
interface WebhookRegistry
{
    /**
     * @param  class-string  $handler
     */
    public function register(string $topic, string $handler): void;

    /**
     * @return array<string, class-string>
     */
    public function handlers(): array;

    public function handlerFor(string $topic): ?string;
}
