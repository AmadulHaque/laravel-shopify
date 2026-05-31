<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\StoreResolver;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Resolves the store the current request acts on.
 *
 * Wraps the default {@see StoreResolver} and lets host apps override resolution
 * at runtime (e.g. session- or auth-based) via {@see self::using()}.
 */
final class CurrentStore
{
    private ?Closure $override = null;

    public function __construct(
        private readonly StoreResolver $resolver,
        private readonly Container $container,
    ) {}

    /**
     * Override how the current store is resolved.
     *
     * @param  Closure(Request): ?ShopModel  $callback
     */
    public function using(Closure $callback): void
    {
        $this->override = $callback;
    }

    public function get(): ?ShopModel
    {
        $request = $this->container->make('request');

        if ($this->override !== null) {
            return ($this->override)($request);
        }

        return $this->resolver->resolve($request);
    }
}
