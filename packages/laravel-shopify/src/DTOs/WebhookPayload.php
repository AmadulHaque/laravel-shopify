<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use AmadulHaque\LaravelShopify\Support\ShopDomain;
use Illuminate\Http\Request;

/**
 * A decoded Shopify webhook: its routing metadata (from headers) plus the body.
 */
final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $topic,
        public string $shopDomain,
        public string $webhookId,
        public ?string $apiVersion,
        public ?string $triggeredAt,
        public array $payload,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $body = $request->getContent();

        return new self(
            topic: (string) $request->header('X-Shopify-Topic', ''),
            shopDomain: ShopDomain::normalize((string) $request->header('X-Shopify-Shop-Domain', '')),
            webhookId: self::resolveWebhookId($request, $body),
            apiVersion: $request->header('X-Shopify-API-Version'),
            triggeredAt: $request->header('X-Shopify-Triggered-At'),
            payload: self::decode($body),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            topic: (string) ($attributes['topic'] ?? ''),
            shopDomain: (string) ($attributes['shop_domain'] ?? ''),
            webhookId: (string) ($attributes['webhook_id'] ?? ''),
            apiVersion: $attributes['api_version'] ?? null,
            triggeredAt: $attributes['triggered_at'] ?? null,
            payload: is_array($attributes['payload'] ?? null) ? $attributes['payload'] : [],
        );
    }

    /**
     * Falls back to a content hash when the webhook-id header is absent, so
     * idempotency still holds for replays of the same body.
     */
    private static function resolveWebhookId(Request $request, string $body): string
    {
        $header = (string) $request->header('X-Shopify-Webhook-Id', '');

        return $header !== '' ? $header : hash('sha256', $body);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
