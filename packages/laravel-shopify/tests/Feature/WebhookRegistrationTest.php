<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Actions\RegisterWebhooks;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->store = Store::factory()->create([
        'shop_domain' => 'demo.myshopify.com',
        'access_token' => 'shpat_token',
    ]);
});

it('registers each configured topic via GraphQL with the correct topic and callback', function (): void {
    Http::fake([
        '*/graphql.json' => Http::response([
            'data' => ['webhookSubscriptionCreate' => [
                'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                'userErrors' => [],
            ]],
        ]),
    ]);

    $results = app(RegisterWebhooks::class)($this->store);

    expect($results['APP_UNINSTALLED']['success'])->toBeTrue()
        ->and($results['APP_UNINSTALLED']['id'])->toBe('gid://shopify/WebhookSubscription/1');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/admin/api/2025-07/graphql.json')
            && data_get($request->data(), 'variables.topic') === 'APP_UNINSTALLED'
            && data_get($request->data(), 'variables.webhookSubscription.callbackUrl') === 'https://app.test/shopify/webhooks'
            && data_get($request->data(), 'variables.webhookSubscription.format') === 'JSON';
    });
});

it('surfaces userErrors as a failed registration', function (): void {
    Http::fake([
        '*/graphql.json' => Http::response([
            'data' => ['webhookSubscriptionCreate' => [
                'webhookSubscription' => null,
                'userErrors' => [['field' => ['topic'], 'message' => 'Address for this topic has already been taken']],
            ]],
        ]),
    ]);

    $results = app(RegisterWebhooks::class)($this->store);

    expect($results['APP_UNINSTALLED']['success'])->toBeFalse()
        ->and($results['APP_UNINSTALLED']['id'])->toBeNull()
        ->and($results['APP_UNINSTALLED']['errors'])->toContain('Address for this topic has already been taken');
});
