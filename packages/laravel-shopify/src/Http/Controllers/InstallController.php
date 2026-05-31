<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Controllers;

use AmadulHaque\LaravelShopify\Actions\BuildAuthorizeUrl;
use AmadulHaque\LaravelShopify\Support\Nonce;
use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point of the OAuth flow: redirects a merchant to Shopify's consent screen.
 */
final readonly class InstallController
{
    public function __construct(
        private BuildAuthorizeUrl $buildAuthorizeUrl,
        private Nonce $nonce,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $shop = ShopDomain::normalize((string) $request->query('shop', ''));

        if (! ShopDomain::isValid($shop)) {
            abort(Response::HTTP_FORBIDDEN, 'A valid ?shop=*.myshopify.com parameter is required.');
        }

        $nonce = $this->nonce->generate();
        $this->nonce->store($nonce, $shop);

        return redirect()->away(($this->buildAuthorizeUrl)($shop, $nonce));
    }
}
