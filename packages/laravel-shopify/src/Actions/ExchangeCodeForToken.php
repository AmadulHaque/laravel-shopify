<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\DTOs\AccessToken;
use AmadulHaque\LaravelShopify\Exceptions\OAuthException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Exchanges an OAuth authorization code for a permanent access token.
 *
 * @see https://shopify.dev/docs/apps/auth/oauth/getting-started#step-5-get-an-access-token
 */
final readonly class ExchangeCodeForToken
{
    public function __construct(
        private HttpFactory $http,
        private Config $config,
    ) {}

    public function __invoke(string $shop, string $code): AccessToken
    {
        $response = $this->http
            ->asJson()
            ->acceptJson()
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => (string) $this->config->get('shopify.api_key'),
                'client_secret' => (string) $this->config->get('shopify.api_secret'),
                'code' => $code,
            ]);

        if ($response->failed()) {
            throw OAuthException::tokenExchangeFailed($shop, $response->status());
        }

        return AccessToken::fromResponse($response->json() ?? []);
    }
}
