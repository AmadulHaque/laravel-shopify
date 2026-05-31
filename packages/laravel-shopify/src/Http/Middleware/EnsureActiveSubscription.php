<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Middleware;

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Support\CurrentStore;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces an active subscription on protected routes.
 *
 * Usage: `->middleware('shopify.billing')` or `->middleware('shopify.billing:pro')`.
 * Resolves the current store; if it has no active plan, creates a subscription
 * and redirects the merchant to Shopify's confirmation page.
 */
final readonly class EnsureActiveSubscription
{
    public function __construct(
        private BillingManager $billing,
        private CurrentStore $currentStore,
    ) {}

    public function handle(Request $request, Closure $next, ?string $plan = null): Response
    {
        $store = $this->currentStore->get();

        if ($store === null) {
            abort(Response::HTTP_FORBIDDEN, 'Unable to resolve the Shopify store for this request.');
        }

        if ($this->billing->hasActivePlan($store, $plan)) {
            return $next($request);
        }

        $confirmation = $this->billing->subscribe($store, $plan);

        return new RedirectResponse($confirmation->confirmationUrl);
    }
}
