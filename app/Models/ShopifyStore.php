<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Models;

use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Database\Factories\StoreFactory;
use AmadulHaque\LaravelShopify\Enums\TokenType;
use AmadulHaque\LaravelShopify\ValueObjects\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A connected Shopify store (one row per installed shop).
 *
 * @property string $shop_domain
 * @property string|null $access_token
 * @property TokenType $token_type
 * @property string|null $scopes
 * @property string|null $plan
 * @property int|null $shop_id
 * @property string|null $email
 * @property string|null $country
 * @property string|null $currency
 * @property Carbon|null $installed_at
 * @property Carbon|null $uninstalled_at
 * @property array<string, mixed>|null $metadata
 */
class Store extends Model implements ShopModel
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'access_token' => 'encrypted',
        'token_type' => TokenType::class,
        'shop_id' => 'integer',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('shopify.store.table', 'shopify_stores');
    }

    public function getShopDomain(): string
    {
        return $this->shop_domain;
    }

    public function getAccessToken(): ?string
    {
        return $this->access_token;
    }

    public function getScopes(): Scopes
    {
        return Scopes::fromString((string) $this->scopes);
    }

    public function getTokenType(): TokenType
    {
        return $this->token_type ?? TokenType::Offline;
    }

    public function isActive(): bool
    {
        return $this->uninstalled_at === null && ! empty($this->access_token);
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }
}
