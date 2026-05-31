<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Events;

use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired (inside the queue) for every verified webhook.
 *
 * Listen for this to fan a single topic out to many handlers, or switch on
 * {@see WebhookPayload::$topic} to route. Registry handlers run alongside it.
 */
final class WebhookReceived
{
    use Dispatchable;

    public function __construct(public readonly WebhookPayload $payload) {}

    public function topic(): string
    {
        return $this->payload->topic;
    }
}
