<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Exceptions;

/**
 * Raised when a billing operation fails (unknown plan, subscription error, etc.).
 */
class BillingException extends ShopifyException
{
    public static function planNotFound(string $plan): self
    {
        return new self("No billing plan named [{$plan}] is configured in shopify.billing.plans.");
    }

    public static function noPlansConfigured(): self
    {
        return new self('No billing plans are configured in shopify.billing.plans.');
    }

    /**
     * @param  list<string>  $errors
     */
    public static function subscribeFailed(string $plan, array $errors): self
    {
        $detail = $errors === [] ? '' : ' '.implode('; ', $errors);

        return new self("Failed to create the [{$plan}] subscription.{$detail}");
    }

    /**
     * @param  list<string>  $errors
     */
    public static function usageRecordFailed(array $errors): self
    {
        $detail = $errors === [] ? '' : ' '.implode('; ', $errors);

        return new self("Failed to record a usage charge.{$detail}");
    }

    public static function noUsageLineItem(string $domain): self
    {
        return new self("Store [{$domain}] has no active usage-pricing line item to bill against.");
    }
}
