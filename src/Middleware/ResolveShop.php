<?php

namespace Decoupled\Shopify\Middleware;

use Closure;
use Decoupled\Shopify\Contracts\ShopResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveShop
{
    public function __construct(private readonly ShopResolver $shops) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shop = $this->shops->resolve($request);
        abort_unless($shop, 404, 'Shop could not be resolved.');
        $request->attributes->set('shopify.shop', $shop);

        return $next($request);
    }
}
