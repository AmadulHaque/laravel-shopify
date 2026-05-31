<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Contracts;

use AmadulHaque\LaravelShopify\Enums\SubscriptionStatus;
use AmadulHaque\LaravelShopify\Enums\TokenType;
use AmadulHaque\LaravelShopify\ValueObjects\Scopes;

/**
 * Contract every persisted Shopify store must satisfy.
 *
 * The API client and OAuth layer depend on this interface, not the concrete
 * Eloquent model, so host applications can swap the model via config.
 */
interface ShopModel
{
    public function getShopDomain(): string;

    public function getAccessToken(): ?string;

    public function getScopes(): Scopes;

    public function getTokenType(): TokenType;

    /**
     * A store is active when it holds a token and has not been uninstalled.
     */
    public function isActive(): bool;

    public function getPlan(): ?string;

    public function getSubscriptionId(): ?string;

    public function getSubscriptionStatus(): ?SubscriptionStatus;

    /**
     * Whether the store currently has an active (paying or trialing) subscription.
     */
    public function isSubscribed(): bool;
}
