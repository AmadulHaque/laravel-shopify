<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Support\Hmac;

const SECRET = 'shhh-this-is-the-app-secret';

function signOAuth(array $params, string $secret = SECRET): string
{
    ksort($params);

    return hash_hmac('sha256', http_build_query($params), $secret);
}

it('verifies a valid OAuth signature', function (): void {
    $params = ['shop' => 'demo.myshopify.com', 'timestamp' => '1700000000'];
    $params['hmac'] = signOAuth($params);

    expect(Hmac::verifyOAuth($params, SECRET))->toBeTrue();
});

it('rejects a tampered OAuth signature', function (): void {
    $params = ['shop' => 'demo.myshopify.com', 'timestamp' => '1700000000'];
    $params['hmac'] = signOAuth($params);
    $params['shop'] = 'evil.myshopify.com';

    expect(Hmac::verifyOAuth($params, SECRET))->toBeFalse();
});

it('rejects an OAuth request with no signature', function (): void {
    expect(Hmac::verifyOAuth(['shop' => 'demo.myshopify.com'], SECRET))->toBeFalse();
});

it('ignores the signature parameter when building the message', function (): void {
    $params = ['shop' => 'demo.myshopify.com', 'code' => 'abc123'];
    $params['hmac'] = signOAuth($params);
    $params['signature'] = 'legacy-value-should-be-ignored';

    expect(Hmac::verifyOAuth($params, SECRET))->toBeTrue();
});

it('verifies a webhook body signature', function (): void {
    $body = '{"id":12345,"topic":"orders/create"}';
    $header = base64_encode(hash_hmac('sha256', $body, SECRET, true));

    expect(Hmac::verifyWebhook($body, $header, SECRET))->toBeTrue()
        ->and(Hmac::verifyWebhook($body.'tampered', $header, SECRET))->toBeFalse()
        ->and(Hmac::verifyWebhook($body, '', SECRET))->toBeFalse();
});
