<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Jobs;

use AmadulHaque\LaravelShopify\Contracts\WebhookHandler;
use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\Events\WebhookReceived;
use AmadulHaque\LaravelShopify\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Processes a verified webhook off the queue.
 *
 * - Idempotent: a webhook already marked processed is skipped.
 * - Retried: honors the configured `tries`/`backoff`.
 * - Dead-lettered: {@see self::failed()} records the terminal failure so the
 *   event can be inspected and replayed (Phase 6) rather than lost.
 */
final class ProcessWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted (read by the queue worker).
     */
    public int $tries = 5;

    public function __construct(public readonly WebhookEvent $event)
    {
        $this->tries = (int) config('shopify.webhooks.queue.tries', 5);
        $this->onConnection(config('shopify.webhooks.queue.connection'));
        $this->onQueue(config('shopify.webhooks.queue.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('shopify.webhooks.queue.backoff', [10, 30, 60]);

        return $backoff;
    }

    public function handle(WebhookRegistry $registry, Dispatcher $events, Container $container): void
    {
        $this->event->refresh();

        if ($this->event->isProcessed()) {
            return;
        }

        $this->event->markProcessing();

        $payload = $this->event->toPayload();

        $events->dispatch(new WebhookReceived($payload));

        $handlerClass = $registry->handlerFor($payload->topic);

        if ($handlerClass !== null) {
            /** @var WebhookHandler $handler */
            $handler = $container->make($handlerClass);
            $handler->handle($payload);
        }

        $this->event->markProcessed();
    }

    public function failed(Throwable $exception): void
    {
        $this->event->markFailed($exception->getMessage());
    }

    /**
     * The unique-job identifier, so duplicate dispatches collapse on a queue
     * driver that supports it.
     */
    public function uniqueId(): string
    {
        return $this->event->webhook_id;
    }
}
