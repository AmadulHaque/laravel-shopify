<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Support\Shop;
use Amadulhaque\Shopify\Webhooks\WebhookSubscription;

interface WebhookRepository
{
    /** @return iterable<WebhookSubscription> */
    public function desiredFor(Shop $shop): iterable;
}
