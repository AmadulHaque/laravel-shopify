<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Contracts\StoreResolver;
use Illuminate\Http\Request;

/**
 * Default store resolver: reads the `shop` route or query parameter, validates
 * it as a myshopify domain, and looks the store up via the repository.
 */
final readonly class RequestStoreResolver implements StoreResolver
{
    public function __construct(private StoreRepository $stores) {}

    public function resolve(Request $request): ?ShopModel
    {
        $shop = (string) ($request->route('shop') ?? $request->query('shop', ''));

        if ($shop === '' || ! ShopDomain::isValid($shop)) {
            return null;
        }

        return $this->stores->findByDomain(ShopDomain::normalize($shop));
    }
}
