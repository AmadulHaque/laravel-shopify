<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\Enums\TokenType;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Builds the Shopify OAuth authorization URL a merchant is redirected to.
 *
 * @see https://shopify.dev/docs/apps/auth/oauth/getting-started
 */
final readonly class BuildAuthorizeUrl
{
    public function __construct(private Config $config) {}

    public function __invoke(string $shop, string $nonce): string
    {
        $query = http_build_query([
            'client_id' => (string) $this->config->get('shopify.api_key'),
            'scope' => (string) $this->config->get('shopify.scopes'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $nonce,
        ]);

        // Online tokens additionally require grant_options[]=per-user.
        if ($this->config->get('shopify.oauth.token_type') === TokenType::Online->value) {
            $query .= '&grant_options[]=per-user';
        } else {
            $query .= '&grant_options[]=';
        }

        return "https://{$shop}/admin/oauth/authorize?{$query}";
    }

    private function redirectUri(): string
    {
        $base = rtrim((string) $this->config->get('shopify.app_url'), '/');
        $prefix = trim((string) $this->config->get('shopify.routes.prefix'), '/');
        $callback = trim((string) $this->config->get('shopify.routes.callback'), '/');

        return "{$base}/{$prefix}/{$callback}";
    }
}
