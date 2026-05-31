<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Compute the base64 webhook HMAC for a raw body.
 */
function webhookHmac(string $body, string $secret = 'test-api-secret'): string
{
    return base64_encode(hash_hmac('sha256', $body, $secret, true));
}

/**
 * POST a webhook with correctly-signed headers and a raw JSON body.
 *
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function postWebhook(string $topic, array $body, array $headers = []): TestResponse
{
    $json = json_encode($body);

    $headers = array_merge([
        'X-Shopify-Topic' => $topic,
        'X-Shopify-Shop-Domain' => 'demo.myshopify.com',
        'X-Shopify-Webhook-Id' => 'evt-'.$topic,
        'X-Shopify-API-Version' => '2025-07',
        'X-Shopify-Hmac-Sha256' => webhookHmac($json),
        'Content-Type' => 'application/json',
    ], $headers);

    return test()->call('POST', '/shopify/webhooks', [], [], [], serverVars($headers), $json);
}

/**
 * Transform a header map into PSR/CGI server variables for TestCase::call().
 *
 * @param  array<string, string>  $headers
 * @return array<string, string>
 */
function serverVars(array $headers): array
{
    $server = [];

    foreach ($headers as $name => $value) {
        $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

        if (strtolower($name) === 'content-type') {
            $key = 'CONTENT_TYPE';
        }

        $server[$key] = $value;
    }

    return $server;
}
