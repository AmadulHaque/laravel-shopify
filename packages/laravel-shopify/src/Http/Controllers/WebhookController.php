<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Http\Controllers;

use AmadulHaque\LaravelShopify\DTOs\WebhookPayload;
use AmadulHaque\LaravelShopify\Enums\WebhookStatus;
use AmadulHaque\LaravelShopify\Jobs\ProcessWebhook;
use AmadulHaque\LaravelShopify\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single ingestion endpoint for all webhook topics (the topic is in the header).
 *
 * HMAC is verified upstream by the `shopify.webhook` middleware. This controller
 * does the bare minimum synchronously — dedupe + persist + enqueue — and returns
 * 200 fast so Shopify does not retry; all real work happens in {@see ProcessWebhook}.
 */
final class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = WebhookPayload::fromRequest($request);

        if ($payload->topic === '' || $payload->shopDomain === '') {
            return new JsonResponse(['received' => false], Response::HTTP_BAD_REQUEST);
        }

        $event = WebhookEvent::firstOrCreate(
            ['webhook_id' => $payload->webhookId],
            [
                'shop_domain' => $payload->shopDomain,
                'topic' => $payload->topic,
                'api_version' => $payload->apiVersion,
                'payload' => $payload->payload,
                'status' => WebhookStatus::Pending,
            ],
        );

        // Idempotency: only the first receipt of a given webhook id is queued.
        if ($event->wasRecentlyCreated) {
            ProcessWebhook::dispatch($event);
        }

        return new JsonResponse([
            'received' => true,
            'duplicate' => ! $event->wasRecentlyCreated,
        ]);
    }
}
