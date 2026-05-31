<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Contracts\WebhookHandler;
use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Enums\WebhookStatus;
use AmadulHaque\LaravelShopify\Events\WebhookReceived;
use AmadulHaque\LaravelShopify\Jobs\ProcessWebhook;
use AmadulHaque\LaravelShopify\Models\WebhookEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

/**
 * Records every payload it handles so tests can assert invocation.
 */
class SpyWebhookHandler implements WebhookHandler
{
    /** @var list<string> */
    public static array $handled = [];

    public function handle(WebhookPayload $payload): void
    {
        self::$handled[] = $payload->topic;
    }
}

class ExplodingWebhookHandler implements WebhookHandler
{
    public function handle(WebhookPayload $payload): void
    {
        throw new RuntimeException('handler boom');
    }
}

beforeEach(function (): void {
    SpyWebhookHandler::$handled = [];
});

it('fires WebhookReceived, runs the registered handler and marks the event processed', function (): void {
    Event::fake([WebhookReceived::class]);

    app(WebhookRegistry::class)->register('orders/create', SpyWebhookHandler::class);

    $event = WebhookEvent::factory()->create(['topic' => 'orders/create']);

    (new ProcessWebhook($event))->handle(
        app(WebhookRegistry::class),
        app(Dispatcher::class),
        app(),
    );

    $event->refresh();

    expect($event->status)->toBe(WebhookStatus::Processed)
        ->and($event->processed_at)->not->toBeNull()
        ->and($event->attempts)->toBe(1)
        ->and(SpyWebhookHandler::$handled)->toBe(['orders/create']);

    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $e) => $e->topic() === 'orders/create');
});

it('skips an already-processed event (idempotent at the job level)', function (): void {
    $event = WebhookEvent::factory()->processed()->create(['topic' => 'orders/create']);

    app(WebhookRegistry::class)->register('orders/create', SpyWebhookHandler::class);

    (new ProcessWebhook($event))->handle(
        app(WebhookRegistry::class),
        app(Dispatcher::class),
        app(),
    );

    expect(SpyWebhookHandler::$handled)->toBe([]);
});

it('dead-letters the event when the handler fails after retries', function (): void {
    $event = WebhookEvent::factory()->create(['topic' => 'orders/create']);

    // Simulate the queue calling failed() once retries are exhausted.
    (new ProcessWebhook($event))->failed(new RuntimeException('handler boom'));

    $event->refresh();

    expect($event->status)->toBe(WebhookStatus::Failed)
        ->and($event->failed_at)->not->toBeNull()
        ->and($event->last_error)->toBe('handler boom');
});

it('processes the webhook end-to-end through the sync queue', function (): void {
    app(WebhookRegistry::class)->register('orders/create', SpyWebhookHandler::class);

    postWebhook('orders/create', ['id' => 5])->assertOk();

    $event = WebhookEvent::firstWhere('webhook_id', 'evt-orders/create');

    expect($event->status)->toBe(WebhookStatus::Processed)
        ->and(SpyWebhookHandler::$handled)->toBe(['orders/create']);
});
