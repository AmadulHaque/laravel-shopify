<?php

namespace Amadulhaque\Shopify\Events;

use Amadulhaque\Shopify\Webhooks\Webhook;

final readonly class WebhookVerified
{
    public function __construct(public Webhook $webhook) {}
}
