<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Billing;

/**
 * An immutable billing plan defined in configuration.
 *
 * A plan always has a recurring price and may additionally carry a usage
 * pricing component (capped amount + terms) for usage-based billing.
 */
final readonly class Plan
{
    /**
     * @param  array{capped_amount: float, terms: string}|null  $usage
     */
    public function __construct(
        public string $name,
        public float $price,
        public string $currency,
        public string $interval,
        public int $trialDays,
        public ?array $usage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $usage = null;

        if (is_array($config['usage'] ?? null)) {
            $usage = [
                'capped_amount' => (float) ($config['usage']['capped_amount'] ?? 0),
                'terms' => (string) ($config['usage']['terms'] ?? ''),
            ];
        }

        return new self(
            name: $name,
            price: (float) ($config['price'] ?? 0),
            currency: (string) ($config['currency'] ?? 'USD'),
            interval: (string) ($config['interval'] ?? 'EVERY_30_DAYS'),
            trialDays: (int) ($config['trial_days'] ?? 0),
            usage: $usage,
        );
    }

    public function hasUsagePricing(): bool
    {
        return $this->usage !== null;
    }
}
