<?php

namespace Amadulhaque\Shopify\Authentication;

use Amadulhaque\Shopify\Contracts\HttpClient;
use Amadulhaque\Shopify\Contracts\OAuthStateRepository;
use Amadulhaque\Shopify\Events\AccessTokenUpdated;
use Amadulhaque\Shopify\Events\ShopInstalled;
use Amadulhaque\Shopify\Exceptions\ShopifyHttpException;
use Amadulhaque\Shopify\Http\HttpRequest;
use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class OAuthService
{
    private ?Shop $configuredShop = null;

    private ?AccessToken $configuredToken = null;

    public function __construct(
        private readonly OAuthStateRepository $states,
        private readonly HttpClient $http,
        private readonly Dispatcher $events,
        private readonly StateGenerator $stateGenerator,
    ) {}

    /**
     * Configure the service for one shop. A supplied token is not stored by the
     * package; call accessTokenIsValid() to verify it with Shopify.
     */
    public function for(string|Shop $shop, string|AccessToken|null $accessToken = null): self
    {
        $copy = clone $this;
        $copy->configuredShop = Shop::from($shop);
        $copy->configuredToken = match (true) {
            $accessToken instanceof AccessToken => $accessToken,
            is_string($accessToken) && trim($accessToken) !== '' => new AccessToken(trim($accessToken)),
            $accessToken === null => null,
            default => throw new \InvalidArgumentException('Shopify access token must not be empty.'),
        };

        return $copy;
    }

    /** @param array<string, mixed> $state */
    public function authorizationUrl(string|Shop|null $shop = null, ?string $callbackUrl = null, array $state = []): string
    {
        $shop = $this->shop($shop);
        $stateToken = $this->stateGenerator->generate();
        $this->states->put($stateToken, $shop, now()->addMinutes(10), $state);

        return "https://{$shop->domain}/admin/oauth/authorize?".http_build_query([
            'client_id' => config('shopify.client_id'),
            'scope' => implode(',', config('shopify.scopes', [])),
            'redirect_uri' => $this->redirectUri($callbackUrl),
            'state' => $stateToken,
        ]);
    }

    /** @param array<string, mixed> $state */
    public function redirect(string|Shop|null $shop = null, ?string $callbackUrl = null, array $state = []): string
    {
        return $this->authorizationUrl($shop, $callbackUrl, $state);
    }

    /**
     * Check that the configured access token can access the configured shop.
     * A failed or cross-shop token returns false; HTTP transport failures still
     * raise ShopifyHttpException.
     */
    public function accessTokenIsValid(): bool
    {
        $shop = $this->configuredShop ?? throw new \LogicException('Configure a shop before validating an access token.');
        $token = $this->configuredToken ?? throw new \LogicException('Provide an access token before validating it.');
        $response = $this->http->send(new HttpRequest(
            'POST',
            "https://{$shop->domain}/admin/api/".config('shopify.api_version').'/graphql.json',
            ['Accept' => 'application/json', 'X-Shopify-Access-Token' => $token->value],
            ['query' => 'query ValidateAccessToken { shop { myshopifyDomain } }', 'variables' => []],
            config('shopify.http.timeout'),
            config('shopify.http.retries'),
            config('shopify.http.retry_delay_ms'),
        ));

        if (! $response->successful()) {
            return false;
        }

        return strtolower((string) ($response->json['data']['shop']['myshopifyDomain'] ?? '')) === $shop->domain;
    }

    public function exchange(Request $request): OAuthResult
    {
        $request->validate([
            'code' => ['required', 'string'],
            'hmac' => ['required', 'string'],
            'shop' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $shop = Shop::from($request->string('shop')->toString());
        if (! $this->hasValidHmac($request)) {
            abort(403, 'Invalid Shopify OAuth callback.');
        }

        $state = $this->states->pull($request->string('state')->toString());
        if ($state?->shop->domain !== $shop->domain) {
            abort(403, 'Invalid Shopify OAuth callback.');
        }

        $response = $this->http->send(new HttpRequest(
            'POST',
            "https://{$shop->domain}/admin/oauth/access_token",
            ['Accept' => 'application/json'],
            [
                'client_id' => config('shopify.client_id'),
                'client_secret' => config('shopify.client_secret'),
                'code' => $request->string('code')->toString(),
            ],
            config('shopify.http.timeout'),
            config('shopify.http.retries'),
            config('shopify.http.retry_delay_ms'),
        ));

        if (! $response->successful() || ! isset($response->json['access_token'])) {
            throw new ShopifyHttpException($response);
        }

        $token = new AccessToken(
            $response->json['access_token'],
            array_filter(explode(',', $response->json['scope'] ?? '')),
            isset($response->json['expires_in']) ? now()->addSeconds((int) $response->json['expires_in'])->toImmutable() : null,
            $response->json['refresh_token'] ?? null,
        );
        $result = new OAuthResult($shop, $token, $state->data);
        $this->events->dispatch(new ShopInstalled($result));
        $this->events->dispatch(new AccessTokenUpdated($result));

        return $result;
    }

    private function hasValidHmac(Request $request): bool
    {
        $parameters = $request->query();
        $hmac = $parameters['hmac'] ?? '';
        unset($parameters['hmac']);
        ksort($parameters);

        return hash_equals(hash_hmac('sha256', http_build_query($parameters), config('shopify.client_secret')), $hmac);
    }

    private function redirectUri(?string $callbackUrl): ?string
    {
        if ($callbackUrl === null) {
            return config('shopify.redirect_uri');
        }

        $parts = parse_url($callbackUrl);
        if (
            filter_var($callbackUrl, FILTER_VALIDATE_URL) === false
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \InvalidArgumentException('Shopify callback URL must be a valid HTTPS URL.');
        }

        return $callbackUrl;
    }

    private function shop(string|Shop|null $shop): Shop
    {
        if ($shop !== null) {
            return Shop::from($shop);
        }

        return $this->configuredShop ?? throw new \LogicException('Provide a shop domain or configure one with oauth($shopDomain).');
    }
}
