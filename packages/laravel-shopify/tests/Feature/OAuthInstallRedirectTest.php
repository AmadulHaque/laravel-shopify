<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

function signInstall(array $params): string
{
    ksort($params);

    return hash_hmac('sha256', http_build_query($params), 'test-api-secret');
}

it('redirects a valid install request to the Shopify consent screen', function (): void {
    $params = ['shop' => 'demo.myshopify.com', 'timestamp' => '1700000000'];
    $params['hmac'] = signInstall($params);

    $response = $this->get('/shopify/install?'.http_build_query($params));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    $parts = parse_url($location);
    parse_str($parts['query'], $query);

    expect($parts['host'])->toBe('demo.myshopify.com')
        ->and($parts['path'])->toBe('/admin/oauth/authorize')
        ->and($query['client_id'])->toBe('test-api-key')
        ->and($query['scope'])->toBe('read_products,write_products')
        ->and($query['redirect_uri'])->toBe('https://app.test/shopify/callback')
        ->and($query['state'])->not->toBeEmpty();

    // The issued nonce is persisted server-side, bound to the shop.
    expect(Cache::get('shopify_oauth_nonce:'.$query['state']))->toBe('demo.myshopify.com');
});

it('rejects an install request whose HMAC is invalid', function (): void {
    $response = $this->get('/shopify/install?shop=demo.myshopify.com&hmac=deadbeef');

    $response->assertForbidden();
});

it('rejects an install request for a non-myshopify domain', function (): void {
    $params = ['shop' => 'evil.example.com', 'timestamp' => '1700000000'];
    $params['hmac'] = signInstall($params);

    $response = $this->get('/shopify/install?'.http_build_query($params));

    $response->assertForbidden();
});
