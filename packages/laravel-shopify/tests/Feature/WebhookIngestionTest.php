<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Enums\WebhookStatus;
use AmadulHaque\LaravelShopify\Jobs\ProcessWebhook;
use AmadulHaque\LaravelShopify\Models\WebhookEvent;
use Illuminate\Support\Facades\Bus;

it('verifies, persists and queues a valid webhook', function (): void {
    Bus::fake();

    $response = postWebhook('orders/create', ['id' => 1001]);

    $response->assertOk()->assertJson(['received' => true, 'duplicate' => false]);

    $event = WebhookEvent::firstWhere('webhook_id', 'evt-orders/create');

    expect($event)->not->toBeNull()
        ->and($event->topic)->toBe('orders/create')
        ->and($event->shop_domain)->toBe('demo.myshopify.com')
        ->and($event->payload)->toBe(['id' => 1001])
        ->and($event->status)->toBe(WebhookStatus::Pending);

    Bus::assertDispatched(ProcessWebhook::class, fn (ProcessWebhook $job) => $job->event->is($event));
});

it('rejects a webhook with an invalid HMAC', function (): void {
    Bus::fake();

    $response = postWebhook('orders/create', ['id' => 1], ['X-Shopify-Hmac-Sha256' => 'not-valid']);

    $response->assertUnauthorized();
    expect(WebhookEvent::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('is idempotent: a duplicate webhook id is stored once and queued once', function (): void {
    Bus::fake();

    postWebhook('orders/create', ['id' => 1001])->assertOk();
    $second = postWebhook('orders/create', ['id' => 1001]);

    $second->assertOk()->assertJson(['received' => true, 'duplicate' => true]);

    expect(WebhookEvent::where('webhook_id', 'evt-orders/create')->count())->toBe(1);
    Bus::assertDispatchedTimes(ProcessWebhook::class, 1);
});

it('rejects a webhook missing its topic header', function (): void {
    Bus::fake();

    $json = json_encode(['id' => 1]);

    $response = $this->call('POST', '/shopify/webhooks', [], [], [], serverVars([
        'X-Shopify-Shop-Domain' => 'demo.myshopify.com',
        'X-Shopify-Webhook-Id' => 'evt-x',
        'X-Shopify-Hmac-Sha256' => webhookHmac($json),
        'Content-Type' => 'application/json',
    ]), $json);

    $response->assertStatus(400);
    Bus::assertNothingDispatched();
});
