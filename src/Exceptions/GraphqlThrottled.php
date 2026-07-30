<?php

namespace Amadulhaque\Shopify\Exceptions;

use RuntimeException;

class GraphqlThrottled extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Shopify GraphQL is throttled; retry after {$retryAfterSeconds} second(s).");
    }
}
