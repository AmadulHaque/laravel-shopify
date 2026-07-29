<?php

namespace Decoupled\Shopify\Authentication;

use DateTimeImmutable;
use Decoupled\Shopify\Contracts\HttpClient;
use Decoupled\Shopify\Contracts\OAuthStateRepository;
use Decoupled\Shopify\Events\AccessTokenUpdated;
use Decoupled\Shopify\Events\ShopInstalled;
use Decoupled\Shopify\Exceptions\ShopifyHttpException;
use Decoupled\Shopify\Http\HttpRequest;
use Decoupled\Shopify\Support\AccessToken;
use Decoupled\Shopify\Support\Shop;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class OAuthService
{
    public function __construct(
        private readonly OAuthStateRepository $states,
        private readonly HttpClient $http,
        private readonly Dispatcher $events,
        private readonly StateGenerator $stateGenerator,
    ) {}

    public function authorizationUrl(string|Shop $shop): string
    {
        $shop = Shop::from($shop);
        $state = $this->stateGenerator->generate();
        $this->states->put($state, $shop, now()->addMinutes(10));

        return "https://{$shop->domain}/admin/oauth/authorize?".http_build_query([
            'client_id' => config('shopify.client_id'),
            'scope' => implode(',', config('shopify.scopes', [])),
            'redirect_uri' => config('shopify.redirect_uri') ?: config('shopify.redirect'),
            'state' => $state,
        ]);
    }

    public function redirect(string|Shop $shop): \Illuminate\Http\RedirectResponse
    {
        return redirect()->away($this->authorizationUrl($shop));
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
        if (! $this->hasValidHmac($request) || $this->states->pull($request->string('state')->toString())?->domain !== $shop->domain) {
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
        $result = new OAuthResult($shop, $token);
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
}
