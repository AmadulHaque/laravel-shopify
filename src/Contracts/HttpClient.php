<?php

namespace Decoupled\Shopify\Contracts;

use Decoupled\Shopify\Http\HttpRequest;
use Decoupled\Shopify\Http\HttpResponse;

interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;
}
