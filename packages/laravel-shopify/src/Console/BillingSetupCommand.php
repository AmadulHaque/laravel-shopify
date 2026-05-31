<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Console;

use AmadulHaque\LaravelShopify\Billing\PlanRepository;
use AmadulHaque\LaravelShopify\Contracts\BillingManager;
use AmadulHaque\LaravelShopify\Contracts\StoreRepository;
use Illuminate\Console\Command;

/**
 * Lists configured billing plans, or subscribes a store and prints the
 * confirmation URL the merchant must approve.
 */
final class BillingSetupCommand extends Command
{
    protected $signature = 'shopify:billing:setup
        {--shop= : Subscribe this shop domain}
        {--plan= : The plan to subscribe to (defaults to the configured default plan)}';

    protected $description = 'List billing plans, or subscribe a store to a plan.';

    public function handle(PlanRepository $plans, StoreRepository $stores, BillingManager $billing): int
    {
        $shop = $this->option('shop');

        if ($shop === null) {
            $this->listPlans($plans);

            return self::SUCCESS;
        }

        $store = $stores->findByDomain($shop);

        if ($store === null) {
            $this->components->error("No store found for [{$shop}]. Install the app first.");

            return self::FAILURE;
        }

        $confirmation = $billing->subscribe($store, $this->option('plan'));

        $this->components->info('Subscription created. Approve it at:');
        $this->line('  '.$confirmation->confirmationUrl);

        return self::SUCCESS;
    }

    private function listPlans(PlanRepository $plans): void
    {
        $rows = [];

        foreach ($plans->all() as $plan) {
            $rows[] = [
                $plan->name,
                number_format($plan->price, 2).' '.$plan->currency,
                $plan->interval,
                $plan->trialDays.'d',
                $plan->hasUsagePricing() ? 'yes' : 'no',
            ];
        }

        $this->table(['Plan', 'Price', 'Interval', 'Trial', 'Usage'], $rows);
    }
}
