<?php

namespace Amadulhaque\Shopify\Contracts;

use Amadulhaque\Shopify\Http\HttpRequest;
use Amadulhaque\Shopify\Http\HttpResponse;

interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;
}
