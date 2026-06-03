<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Facades\Shopify;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Support\Facades\Route;

it('passes an installed store through to the protected route', function (): void {
    Route::middleware(['web', 'shopify.auth'])->get('/app', fn () => 'dashboard');

    Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);

    $this->get('/app?shop=demo.myshopify.com')->assertOk()->assertSee('dashboard');
});

it('redirects an unknown shop into the OAuth install flow', function (): void {
    Route::middleware(['web', 'shopify.auth'])->get('/app', fn () => 'dashboard');

    $this->get('/app?shop=demo.myshopify.com')
        ->assertRedirect(route('shopify.install', ['shop' => 'demo.myshopify.com']));
});

it('redirects an uninstalled store back into the OAuth install flow', function (): void {
    Route::middleware(['web', 'shopify.auth'])->get('/app', fn () => 'dashboard');

    Store::factory()->uninstalled()->create(['shop_domain' => 'demo.myshopify.com']);

    $this->get('/app?shop=demo.myshopify.com')
        ->assertRedirect(route('shopify.install', ['shop' => 'demo.myshopify.com']));
});

it('forbids the request when no valid shop can be determined', function (): void {
    Route::middleware(['web', 'shopify.auth'])->get('/app', fn () => 'dashboard');

    $this->get('/app')->assertForbidden();
    $this->get('/app?shop=evil.example.com')->assertForbidden();
});

it('honors a custom store resolver override', function (): void {
    Route::middleware(['web', 'shopify.auth'])->get('/app', fn () => 'dashboard');

    $store = Store::factory()->create(['shop_domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x']);
    Shopify::resolveStoreUsing(fn () => $store);

    $this->get('/app')->assertOk()->assertSee('dashboard');
});

it('begins OAuth for a redirect-initiated install that carries no HMAC', function (): void {
    $response = $this->get('/shopify/install?shop=demo.myshopify.com');

    $response->assertRedirect();
    expect(parse_url((string) $response->headers->get('Location'), PHP_URL_PATH))
        ->toBe('/admin/oauth/authorize');
});
