<?php

namespace Decoupled\Shopify\Events;

use Decoupled\Shopify\Webhooks\Webhook;

final readonly class WebhookVerified
{
    public function __construct(public Webhook $webhook) {}
}
