<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Middleware;

use AmadulHaque\LaravelShopify\Support\Hmac;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HMAC signature on inbound Shopify OAuth requests.
 *
 * Applied to the install and callback routes so controllers can trust the query.
 */
final readonly class VerifyShopifyOAuth
{
    public function __construct(private Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $this->config->get('shopify.api_secret');

        if (! Hmac::verifyOAuth($request->query(), $secret)) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid Shopify OAuth HMAC signature.');
        }

        return $next($request);
    }
}
