<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Controllers;

use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use AmadulHaque\LaravelShopify\Events\SubscriptionActivated;
use AmadulHaque\LaravelShopify\Events\SubscriptionDeclined;
use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Billing return_url handler.
 *
 * After the merchant approves (or declines) the charge, Shopify redirects here.
 * We re-fetch the active subscription (source of truth), persist it, fire the
 * matching event, and redirect the merchant back into the app.
 */
final readonly class BillingConfirmController
{
    public function __construct(
        private BillingManager $billing,
        private StoreRepository $stores,
        private Config $config,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $shop = ShopDomain::normalize((string) $request->query('shop', ''));

        $store = ShopDomain::isValid($shop) ? $this->stores->findByDomain($shop) : null;

        if ($store === null) {
            abort(Response::HTTP_FORBIDDEN, 'Unknown or invalid shop for billing confirmation.');
        }

        $subscription = $this->billing->activeSubscription($store);

        if ($subscription === null || ! $subscription->isActive()) {
            SubscriptionDeclined::dispatch($store);

            return new RedirectResponse($this->redirectAfter().'?billing=declined');
        }

        $this->stores->saveSubscription($store->getShopDomain(), $subscription->name, $subscription);

        SubscriptionActivated::dispatch($store, $subscription);

        return new RedirectResponse($this->redirectAfter().'?billing=activated');
    }

    private function redirectAfter(): string
    {
        return (string) $this->config->get('shopify.billing.routes.redirect_after', '/');
    }
}
