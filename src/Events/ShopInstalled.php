<?php

namespace Decoupled\Shopify\Events;

use Decoupled\Shopify\Authentication\OAuthResult;

final readonly class ShopInstalled
{
    public function __construct(public OAuthResult $result) {}
}
