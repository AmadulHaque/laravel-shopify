<?php

namespace Amadulhaque\Shopify\Events;

use Amadulhaque\Shopify\Support\Shop;
use Amadulhaque\Shopify\Webhooks\Webhook;

final readonly class ShopUninstalled
{
    public function __construct(public Shop $shop, public Webhook $webhook) {}
}
