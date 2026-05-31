<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Webhooks\Handlers;

use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Contracts\WebhookHandler;
use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Events\StoreUninstalled;

/**
 * Built-in handler for the `app/uninstalled` topic.
 *
 * Deactivates the store (clearing its now-revoked token) and fires
 * {@see StoreUninstalled} so the host app can run its own cleanup.
 */
final readonly class HandleAppUninstalled implements WebhookHandler
{
    public function __construct(private StoreRepository $stores) {}

    public function handle(WebhookPayload $payload): void
    {
        $this->stores->markUninstalled($payload->shopDomain);

        StoreUninstalled::dispatch($payload->shopDomain);
    }
}
