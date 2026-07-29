<?php

namespace Decoupled\Shopify\Http;

use Decoupled\Shopify\Contracts\HttpClient;
use Illuminate\Http\Client\Factory;

class LaravelHttpClient implements HttpClient
{
    public function __construct(private readonly Factory $http) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $response = $this->http
            ->timeout($request->timeout)
            ->retry($request->retries, $request->retryDelayMs, throw: false)
            ->withHeaders($request->headers)
            ->send($request->method, $request->url, ['json' => $request->json]);

        return new HttpResponse($response->status(), $response->json() ?? [], $response->headers());
    }
}
