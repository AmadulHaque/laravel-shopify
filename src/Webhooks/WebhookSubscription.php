<?php

namespace Decoupled\Shopify\Webhooks;

final readonly class WebhookSubscription
{
    public function __construct(public string $topic, public string $callbackUrl, public ?string $id = null) {}
}
