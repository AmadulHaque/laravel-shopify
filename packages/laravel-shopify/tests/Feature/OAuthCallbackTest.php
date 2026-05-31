<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Events\StoreInstalled;
use AmadulHaque\LaravelShopify\Models\Store;
use AmadulHaque\LaravelShopify\Support\Nonce;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function signCallback(array $params): string
{
    ksort($params);

    return hash_hmac('sha256', http_build_query($params), 'test-api-secret');
}

function callbackUrl(string $shop, string $nonce, string $code = 'auth-code'): string
{
    $params = ['code' => $code, 'shop' => $shop, 'state' => $nonce, 'timestamp' => '1700000000'];
    $params['hmac'] = signCallback($params);

    return '/shopify/callback?'.http_build_query($params);
}

beforeEach(function (): void {
    Http::fake([
        '*/admin/oauth/access_token' => Http::response([
            'access_token' => 'shpat_super_secret',
            'scope' => 'read_products,write_products',
        ]),
    ]);
});

it('completes OAuth, persists an encrypted token and fires StoreInstalled', function (): void {
    Event::fake([StoreInstalled::class]);

    $nonce = app(Nonce::class);
    $state = $nonce->generate();
    $nonce->store($state, 'demo.myshopify.com');

    $response = $this->get(callbackUrl('demo.myshopify.com', $state));

    $response->assertOk()->assertJson(['installed' => true, 'shop' => 'demo.myshopify.com']);

    $store = Store::firstWhere('shop_domain', 'demo.myshopify.com');

    expect($store)->not->toBeNull()
        ->and($store->access_token)->toBe('shpat_super_secret') // decrypted via cast
        ->and($store->installed_at)->not->toBeNull()
        ->and($store->getScopes()->toString())->toBe('read_products,write_products');

    // The raw column is ciphertext, not the plaintext token.
    $raw = $store->getRawOriginal('access_token');
    expect($raw)->not->toBe('shpat_super_secret');

    Event::assertDispatched(StoreInstalled::class, fn (StoreInstalled $e) => $e->store->getShopDomain() === 'demo.myshopify.com');
});

it('rejects a callback whose state nonce does not match', function (): void {
    app(Nonce::class)->store('the-real-nonce', 'demo.myshopify.com');

    $response = $this->get(callbackUrl('demo.myshopify.com', 'a-forged-nonce'));

    $response->assertForbidden();
    expect(Store::count())->toBe(0);
});

it('rejects a callback whose HMAC is tampered', function (): void {
    $state = 'some-nonce';
    app(Nonce::class)->store($state, 'demo.myshopify.com');

    $url = callbackUrl('demo.myshopify.com', $state).'&extra=tampered';

    $response = $this->get($url);

    $response->assertForbidden();
    expect(Store::count())->toBe(0);
});
