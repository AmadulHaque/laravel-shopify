<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Database\Factories;

use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;
use AmadulHaque\LaravelShopify\Enums\TokenType;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_domain' => Str::lower($this->faker->unique()->domainWord()).'.myshopify.com',
            'access_token' => 'shpat_'.Str::random(32),
            'token_type' => TokenType::Offline,
            'scopes' => 'read_products,write_products',
            'plan' => 'basic',
            'shop_id' => $this->faker->unique()->numberBetween(1000, 9_999_999),
            'email' => $this->faker->safeEmail(),
            'country' => $this->faker->countryCode(),
            'currency' => $this->faker->currencyCode(),
            'installed_at' => now(),
            'uninstalled_at' => null,
            'metadata' => [],
        ];
    }

    public function uninstalled(): static
    {
        return $this->state(fn (): array => [
            'access_token' => null,
            'uninstalled_at' => now(),
        ]);
    }

    public function subscribed(string $plan = 'pro'): static
    {
        return $this->state(fn (): array => [
            'plan' => $plan,
            'subscription_id' => 'gid://shopify/AppSubscription/'.$this->faker->numberBetween(1, 9999),
            'subscription_status' => SubscriptionStatus::Active,
            'plan_activated_at' => now(),
        ]);
    }
}
