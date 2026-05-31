<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Support\WebhookTopic;
use Illuminate\Http\Request;

it('converts topics between header and enum forms', function (): void {
    expect(WebhookTopic::toEnum('orders/create'))->toBe('ORDERS_CREATE')
        ->and(WebhookTopic::toEnum('app/uninstalled'))->toBe('APP_UNINSTALLED')
        ->and(WebhookTopic::toEnum('ORDERS_CREATE'))->toBe('ORDERS_CREATE')
        ->and(WebhookTopic::toHeader('ORDERS_CREATE'))->toBe('orders/create');
});

it('builds a payload from request headers and body', function (): void {
    $body = json_encode(['id' => 99, 'name' => '#1001']);

    $request = Request::create('/shopify/webhooks', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'Demo.myshopify.com',
        'HTTP_X_SHOPIFY_WEBHOOK_ID' => 'evt-123',
        'HTTP_X_SHOPIFY_API_VERSION' => '2025-07',
    ], $body);

    $payload = WebhookPayload::fromRequest($request);

    expect($payload->topic)->toBe('orders/create')
        ->and($payload->shopDomain)->toBe('demo.myshopify.com')
        ->and($payload->webhookId)->toBe('evt-123')
        ->and($payload->apiVersion)->toBe('2025-07')
        ->and($payload->payload)->toBe(['id' => 99, 'name' => '#1001']);
});

it('falls back to a body hash when the webhook id header is absent', function (): void {
    $body = '{"id":1}';
    $request = Request::create('/shopify/webhooks', 'POST', [], [], [], [
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'demo.myshopify.com',
    ], $body);

    expect(WebhookPayload::fromRequest($request)->webhookId)->toBe(hash('sha256', $body));
});
