<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Billing;

use AmadulHaque\LaravelShopify\Exceptions\BillingException;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reads the billing plans defined in `shopify.billing.plans` as {@see Plan} objects.
 */
final readonly class PlanRepository
{
    public function __construct(private Config $config) {}

    /**
     * @return array<string, Plan>
     */
    public function all(): array
    {
        $plans = [];

        /** @var array<string, array<string, mixed>> $configured */
        $configured = $this->config->get('shopify.billing.plans', []);

        foreach ($configured as $name => $definition) {
            $plans[$name] = Plan::fromConfig($name, $definition);
        }

        return $plans;
    }

    public function find(string $name): Plan
    {
        return $this->all()[$name] ?? throw BillingException::planNotFound($name);
    }

    /**
     * Resolve a plan by name, falling back to the configured default plan.
     */
    public function resolve(?string $name): Plan
    {
        $name ??= (string) $this->config->get('shopify.billing.default_plan');

        if ($name === '') {
            throw BillingException::noPlansConfigured();
        }

        return $this->find($name);
    }

    public function default(): Plan
    {
        return $this->resolve(null);
    }
}
