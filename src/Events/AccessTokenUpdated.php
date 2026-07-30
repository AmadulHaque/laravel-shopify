<?php

namespace Amadulhaque\Shopify\Events;

use Amadulhaque\Shopify\Authentication\OAuthResult;

final readonly class AccessTokenUpdated
{
    public function __construct(public OAuthResult $result) {}
}
