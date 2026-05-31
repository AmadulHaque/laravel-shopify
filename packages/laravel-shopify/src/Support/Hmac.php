<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

/**
 * HMAC verification for Shopify OAuth requests and webhooks.
 *
 * Both comparisons use {@see hash_equals()} for constant-time evaluation to
 * avoid leaking validity through timing.
 */
final class Hmac
{
    /**
     * Verify the `hmac` parameter on an OAuth/redirect request.
     *
     * Shopify signs the query string (minus `hmac`/`signature`), sorted by key,
     * with the app secret using SHA-256, hex-encoded.
     *
     * @param  array<string, mixed>  $query
     */
    public static function verifyOAuth(array $query, string $secret): bool
    {
        $provided = (string) ($query['hmac'] ?? '');

        if ($provided === '') {
            return false;
        }

        unset($query['hmac'], $query['signature']);

        ksort($query);

        $message = http_build_query($query);

        $computed = hash_hmac('sha256', $message, $secret);

        return hash_equals($computed, $provided);
    }

    /**
     * Verify the `X-Shopify-Hmac-Sha256` header on a webhook.
     *
     * Webhooks sign the raw request body with the app secret using SHA-256,
     * base64-encoded.
     */
    public static function verifyWebhook(string $rawBody, string $header, string $secret): bool
    {
        if ($header === '') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($computed, $header);
    }
}
