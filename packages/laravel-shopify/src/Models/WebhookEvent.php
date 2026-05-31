<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Models;

use AmadulHaque\LaravelShopify\Database\Factories\WebhookEventFactory;
use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A received webhook, persisted for idempotency, dead-lettering and replay.
 *
 * @property string $webhook_id
 * @property string $shop_domain
 * @property string $topic
 * @property string|null $api_version
 * @property array<string, mixed> $payload
 * @property WebhookStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property Carbon|null $failed_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'status' => WebhookStatus::class,
        'attempts' => 'integer',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('shopify.webhooks.table', 'shopify_webhook_events');
    }

    public function toPayload(): WebhookPayload
    {
        return WebhookPayload::fromArray([
            'topic' => $this->topic,
            'shop_domain' => $this->shop_domain,
            'webhook_id' => $this->webhook_id,
            'api_version' => $this->api_version,
            'payload' => $this->payload,
        ]);
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => WebhookStatus::Processing,
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'status' => WebhookStatus::Processed,
            'processed_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => WebhookStatus::Failed,
            'failed_at' => now(),
            'last_error' => $error,
        ])->save();
    }

    public function isProcessed(): bool
    {
        return $this->status === WebhookStatus::Processed;
    }

    /**
     * @param  Builder<WebhookEvent>  $query
     * @return Builder<WebhookEvent>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WebhookStatus::Failed);
    }

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }
}
