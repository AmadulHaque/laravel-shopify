<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Middleware;

use AmadulHaque\LaravelShopify\Support\Hmac;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `X-Shopify-Hmac-Sha256` signature on inbound webhooks.
 *
 * Registered as the `shopify.webhook` alias now; the Phase 2 webhook controller
 * and routes consume it.
 */
final readonly class VerifyShopifyWebhook
{
    public function __construct(private Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $this->config->get('shopify.api_secret');
        $header = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if (! Hmac::verifyWebhook($request->getContent(), $header, $secret)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid Shopify webhook HMAC signature.');
        }

        return $next($request);
    }
}
