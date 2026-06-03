<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Middleware;

use AmadulHaque\LaravelShopify\Support\CurrentStore;
use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate application routes behind a connected Shopify store.
 *
 * Resolves the current store for the request; an authenticated (installed and
 * not uninstalled) store passes through. Otherwise the merchant is sent into
 * the OAuth install flow so a fresh access token can be granted.
 */
final readonly class AuthenticateShopify
{
    public function __construct(private CurrentStore $currentStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->currentStore->get();

        if ($store !== null && $store->isActive()) {
            return $next($request);
        }

        $shop = ShopDomain::normalize((string) ($request->route('shop') ?? $request->query('shop', '')));

        if (! ShopDomain::isValid($shop)) {
            abort(Response::HTTP_FORBIDDEN, 'A valid ?shop=*.myshopify.com parameter is required to authenticate with Shopify.');
        }

        return new RedirectResponse(route('shopify.install', ['shop' => $shop]));
    }
}
