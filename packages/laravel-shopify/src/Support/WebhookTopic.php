<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Support;

/**
 * Translates between Shopify's two webhook-topic spellings.
 *
 * Webhook headers and the REST API use the slash form ("orders/create"), while
 * the GraphQL `WebhookSubscriptionTopic` enum uses the screaming-snake form
 * ("ORDERS_CREATE").
 */
final class WebhookTopic
{
    /**
     * Convert a slash-form topic to the GraphQL enum form.
     */
    public static function toEnum(string $topic): string
    {
        return str_replace(['/', '-'], '_', strtoupper(trim($topic)));
    }

    /**
     * Convert a GraphQL enum-form topic to the slash form.
     */
    public static function toHeader(string $enum): string
    {
        return str_replace('_', '/', strtolower(trim($enum)));
    }
}
