<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Controllers;

use AmadulHaque\LaravelShopify\Actions\ExchangeCodeForToken;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\DTOs\OAuthCallbackData;
use AmadulHaque\LaravelShopify\Events\StoreInstalled;
use AmadulHaque\LaravelShopify\Exceptions\OAuthException;
use AmadulHaque\LaravelShopify\Support\Nonce;
use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth callback: verifies the request, exchanges the code, and persists the store.
 *
 * HMAC is already verified by the `shopify.oauth` middleware; this controller
 * additionally validates the `state` nonce (CSRF) before exchanging the code.
 */
final readonly class CallbackController
{
    public function __construct(
        private ExchangeCodeForToken $exchangeCodeForToken,
        private StoreRepository $stores,
        private Nonce $nonce,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $callback = OAuthCallbackData::fromRequest($request);
        $shop = ShopDomain::normalize($callback->shop);

        if (! ShopDomain::isValid($shop)) {
            abort(Response::HTTP_FORBIDDEN, OAuthException::invalidShopDomain($callback->shop)->getMessage());
        }

        if ($this->nonce->pull($callback->state) !== $shop) {
            abort(Response::HTTP_FORBIDDEN, OAuthException::invalidState()->getMessage());
        }

        $token = ($this->exchangeCodeForToken)($shop, $callback->code);

        $store = $this->stores->upsertFromOAuth($shop, $token);

        StoreInstalled::dispatch($store);

        return new JsonResponse([
            'installed' => true,
            'shop' => $store->getShopDomain(),
        ]);
    }
}
