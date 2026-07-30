<?php

namespace Amadulhaque\Shopify\Webhooks;

use Amadulhaque\Shopify\Support\Shop;

final readonly class Webhook
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public Shop $shop,
        public string $topic,
        public string $apiVersion,
        public string $webhookId,
        public array $payload,
        public string $rawBody,
    ) {}
}
