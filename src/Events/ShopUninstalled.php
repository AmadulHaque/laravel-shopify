<?php

namespace Decoupled\Shopify\Events;

use Decoupled\Shopify\Support\Shop;
use Decoupled\Shopify\Webhooks\Webhook;

final readonly class ShopUninstalled
{
    public function __construct(public Shop $shop, public Webhook $webhook) {}
}
