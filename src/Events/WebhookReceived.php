<?php

namespace Amadulhaque\Shopify\Events;

use Amadulhaque\Shopify\Webhooks\Webhook;

final readonly class WebhookReceived
{
    public function __construct(public Webhook $webhook) {}
}
