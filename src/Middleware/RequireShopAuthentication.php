<?php

namespace Decoupled\Shopify\Middleware;

use Closure;
use Decoupled\Shopify\Contracts\AuthorizationResolver;
use Decoupled\Shopify\Contracts\ShopResolver;
use Decoupled\Shopify\Contracts\TokenRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireShopAuthentication
{
    public function __construct(
        private readonly ShopResolver $shops,
        private readonly TokenRepository $tokens,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = $this->shops->resolve($request);
        abort_unless($shop && $this->tokens->findFor($shop), 401, 'Shopify authentication is required.');
        if ($this->container->bound(AuthorizationResolver::class)) {
            abort_unless($this->container->make(AuthorizationResolver::class)->canAccess($request, $shop), 403);
        }
        $request->attributes->set('shopify.shop', $shop);

        return $next($request);
    }
}
