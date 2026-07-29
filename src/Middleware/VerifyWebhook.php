<?php

namespace Decoupled\Shopify\Middleware;

use Closure;
use Decoupled\Shopify\Webhooks\WebhookVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhook
{
    public function __construct(private readonly WebhookVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('shopify.webhook', $this->verifier->verify($request));

        return $next($request);
    }
}
