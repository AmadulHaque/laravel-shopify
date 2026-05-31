<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\DTOs\RestResponse;

/**
 * Manages Shopify billing (recurring + usage charges).
 *
 * Interface-only extension point for the Phase 3 billing system; concrete
 * charge builders and the plan-enforcement middleware are added in that phase.
 */
interface BillingManager
{
    public function createCharge(ShopModel $store, string $plan): RestResponse;

    public function activate(ShopModel $store, string $chargeId): void;

    public function hasActivePlan(ShopModel $store, string $plan): bool;
}
