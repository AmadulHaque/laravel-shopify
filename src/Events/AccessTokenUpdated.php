<?php

namespace Decoupled\Shopify\Events;

use Decoupled\Shopify\Authentication\OAuthResult;

final readonly class AccessTokenUpdated
{
    public function __construct(public OAuthResult $result) {}
}
