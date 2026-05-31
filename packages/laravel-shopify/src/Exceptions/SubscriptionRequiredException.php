<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a store must subscribe before accessing a protected resource.
 *
 * Self-rendering: Laravel calls render() to redirect the merchant to Shopify's
 * subscription confirmation page, enabling the `Shopify::billing()->requirePlan()`
 * one-liner to "just work" from a controller.
 */
class SubscriptionRequiredException extends BillingException
{
    public function __construct(public readonly string $confirmationUrl, string $plan)
    {
        parent::__construct("An active [{$plan}] subscription is required.");
    }

    public function render(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->confirmationUrl);
    }
}
