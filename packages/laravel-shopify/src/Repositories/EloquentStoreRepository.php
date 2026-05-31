<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Repositories;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\DTOs\AccessToken;
use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent-backed store persistence.
 *
 * The concrete model class is resolved from `shopify.store.model`, so host apps
 * can point this at their own model without changing the repository.
 */
final readonly class EloquentStoreRepository implements StoreRepository
{
    /**
     * @param  class-string<Model&ShopModel>  $model
     */
    public function __construct(private string $model) {}

    public function findByDomain(string $domain): ?ShopModel
    {
        return $this->query()->firstWhere('shop_domain', ShopDomain::normalize($domain));
    }

    public function upsertFromOAuth(string $domain, AccessToken $token): ShopModel
    {
        /** @var Model&ShopModel $store */
        $store = $this->query()->updateOrCreate(
            ['shop_domain' => ShopDomain::normalize($domain)],
            [
                'access_token' => $token->token,
                'token_type' => $token->type,
                'scopes' => $token->scopes->toString(),
                'installed_at' => Carbon::now(),
                'uninstalled_at' => null,
            ],
        );

        return $store;
    }

    public function markUninstalled(string $domain): void
    {
        $this->query()
            ->where('shop_domain', ShopDomain::normalize($domain))
            ->update([
                'access_token' => null,
                'uninstalled_at' => Carbon::now(),
            ]);
    }

    /**
     * @return Builder<Model&ShopModel>
     */
    private function query()
    {
        return $this->model::query();
    }
}
