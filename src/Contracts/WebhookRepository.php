<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Support\Shop;
use Decoupled\Shopify\Webhooks\WebhookSubscription;

interface WebhookRepository
{
    /** @return iterable<WebhookSubscription> */
    public function desiredFor(Shop $shop): iterable;
}
