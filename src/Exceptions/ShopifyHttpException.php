<?php

namespace Decoupled\Shopify\Exceptions;

use Decoupled\Shopify\Http\HttpResponse;
use RuntimeException;

class ShopifyHttpException extends RuntimeException
{
    public function __construct(public readonly HttpResponse $response)
    {
        parent::__construct("Shopify returned HTTP {$response->status}.");
    }
}
