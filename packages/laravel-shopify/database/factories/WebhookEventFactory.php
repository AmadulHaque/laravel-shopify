<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Database\Factories;

use AmadulHaque\LaravelShopify\Enums\WebhookStatus;
use AmadulHaque\LaravelShopify\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => (string) Str::uuid(),
            'shop_domain' => Str::lower($this->faker->domainWord()).'.myshopify.com',
            'topic' => 'orders/create',
            'api_version' => '2025-07',
            'payload' => ['id' => $this->faker->numberBetween(1000, 9999)],
            'status' => WebhookStatus::Pending,
            'attempts' => 0,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookStatus::Failed,
            'failed_at' => now(),
            'last_error' => 'Handler threw an exception.',
            'attempts' => 5,
        ]);
    }
}
