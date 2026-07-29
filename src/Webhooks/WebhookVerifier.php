<?php

namespace Decoupled\Shopify\Webhooks;

use Decoupled\Shopify\Events\WebhookReceived;
use Decoupled\Shopify\Events\WebhookVerified;
use Decoupled\Shopify\Events\ShopUninstalled;
use Decoupled\Shopify\Support\Shop;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class WebhookVerifier
{
    public function __construct(private readonly Dispatcher $events) {}

    public function verify(Request $request): Webhook
    {
        $rawBody = $request->getContent();
        $providedHmac = (string) $request->header('X-Shopify-Hmac-Sha256');
        abort_unless($providedHmac !== '', 401, 'Missing Shopify webhook signature.');
        $expectedHmac = base64_encode(hash_hmac('sha256', $rawBody, config('shopify.client_secret'), true));
        if (! hash_equals($expectedHmac, $providedHmac)) {
            abort(401, 'Invalid Shopify webhook signature.');
        }
        $shopDomain = $this->requiredHeader($request, 'X-Shopify-Shop-Domain');
        $topic = $this->requiredHeader($request, 'X-Shopify-Topic');
        $apiVersion = $this->requiredHeader($request, 'X-Shopify-Api-Version');
        $webhookId = $this->requiredHeader($request, 'X-Shopify-Webhook-Id');
        $shop = Shop::from($shopDomain);
        $webhook = new Webhook(
            $shop,
            $topic,
            $apiVersion,
            $webhookId,
            $request->json()->all(),
            $rawBody,
        );
        $this->events->dispatch(new WebhookReceived($webhook));
        $this->events->dispatch(new WebhookVerified($webhook));
        if ($webhook->topic === 'app/uninstalled') {
            $this->events->dispatch(new ShopUninstalled($shop, $webhook));
        }

        return $webhook;
    }

    private function requiredHeader(Request $request, string $header): string
    {
        $value = (string) $request->header($header);
        abort_unless($value !== '', 400, "Missing required Shopify header: {$header}.");

        return $value;
    }
}
