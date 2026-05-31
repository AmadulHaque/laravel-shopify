<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Exceptions\ShopifyApiException;
use AmadulHaque\LaravelShopify\Exceptions\ThrottledException;
use AmadulHaque\LaravelShopify\Facades\Shopify;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function throttledBody(): array
{
    return [
        'errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
        'extensions' => ['cost' => [
            'requestedQueryCost' => 100,
            'throttleStatus' => ['maximumAvailable' => 1000, 'currentlyAvailable' => 0, 'restoreRate' => 50],
        ]],
    ];
}

function successBody(): array
{
    return [
        'data' => ['shop' => ['name' => 'Acme Co']],
        'extensions' => ['cost' => [
            'requestedQueryCost' => 11,
            'throttleStatus' => ['maximumAvailable' => 1000, 'currentlyAvailable' => 989, 'restoreRate' => 50],
        ]],
    ];
}

beforeEach(function (): void {
    Sleep::fake();

    $this->store = Store::factory()->create([
        'shop_domain' => 'test.myshopify.com',
        'access_token' => 'shpat_token',
    ]);
});

it('retries a throttled GraphQL request and returns the eventual success', function (): void {
    Http::fake([
        '*/graphql.json' => Http::sequence()
            ->push(throttledBody(), 200)
            ->push(successBody(), 200),
    ]);

    $response = Shopify::api()->forStore($this->store)->graphql('{ shop { name } }');

    expect($response->data)->toBe(['shop' => ['name' => 'Acme Co']])
        ->and($response->throttled())->toBeFalse();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

it('throws ThrottledException once retries are exhausted', function (): void {
    Http::fake([
        '*/graphql.json' => Http::response(throttledBody(), 200),
    ]);

    expect(fn () => Shopify::api()->forStore($this->store)->graphql('{ shop { name } }'))
        ->toThrow(ThrottledException::class);

    // Initial attempt + 5 configured retries.
    Http::assertSentCount(6);
    Sleep::assertSleptTimes(5);
});

it('honors a REST 429 Retry-After then succeeds', function (): void {
    Http::fake([
        '*/orders.json' => Http::sequence()
            ->push([], 429, ['Retry-After' => '2'])
            ->push(['orders' => []], 200),
    ]);

    $response = Shopify::api()->forStore($this->store)->rest('GET', 'orders.json');

    expect($response->successful())->toBeTrue()
        ->and($response->body)->toBe(['orders' => []]);

    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->milliseconds === 2000, 1);
});

it('throws when no store is bound to the client', function (): void {
    expect(fn () => Shopify::api()->graphql('{ shop { name } }'))
        ->toThrow(ShopifyApiException::class);
});
