<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Events\WebhookReceived;
use AmadulHaque\LaravelShopify\Jobs\ProcessWebhook;

/**
 * A primary handler for a single webhook topic.
 *
 * Handlers run inside the queued {@see ProcessWebhook}
 * job. They are resolved from the container, so they may type-hint dependencies.
 * For one-to-many fan-out, listen for the {@see WebhookReceived}
 * event instead.
 */
interface WebhookHandler
{
    public function handle(WebhookPayload $payload): void;
}
