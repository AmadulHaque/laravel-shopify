<?php

namespace Decoupled\Shopify\Events;

use Decoupled\Shopify\Webhooks\Webhook;

final readonly class WebhookReceived
{
    public function __construct(public Webhook $webhook) {}
}
