<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Webhooks;

use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\Support\WebhookTopic;

/**
 * In-memory webhook topic → handler registry, seeded from configuration.
 *
 * Topics are keyed by their canonical GraphQL enum form so lookups are
 * consistent whether the caller passes "orders/create" or "ORDERS_CREATE".
 */
final class ConfigWebhookRegistry implements WebhookRegistry
{
    /**
     * @var array<string, class-string>
     */
    private array $handlers = [];

    /**
     * @param  array<string, class-string>  $topics
     */
    public function __construct(array $topics = [])
    {
        foreach ($topics as $topic => $handler) {
            $this->register($topic, $handler);
        }
    }

    public function register(string $topic, string $handler): void
    {
        $this->handlers[$this->normalize($topic)] = $handler;
    }

    public function handlers(): array
    {
        return $this->handlers;
    }

    public function handlerFor(string $topic): ?string
    {
        return $this->handlers[$this->normalize($topic)] ?? null;
    }

    public function topics(): array
    {
        return array_keys($this->handlers);
    }

    private function normalize(string $topic): string
    {
        return WebhookTopic::toEnum($topic);
    }
}
