<?php

namespace Amadulhaque\Shopify\GraphQL;

use Amadulhaque\Shopify\Contracts\HttpClient;
use Amadulhaque\Shopify\Contracts\TokenRepository;
use Amadulhaque\Shopify\Exceptions\GraphqlThrottled;
use Amadulhaque\Shopify\Exceptions\MissingContractBinding;
use Amadulhaque\Shopify\Exceptions\ShopifyHttpException;
use Amadulhaque\Shopify\Http\HttpRequest;
use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;
use Illuminate\Contracts\Container\Container;

class GraphqlClient
{
    private ?Shop $shop = null;

    private ?AccessToken $accessToken = null;

    /** @var array<string, mixed> */
    private array $variables = [];

    public function __construct(private readonly Container $container, private readonly HttpClient $http) {}

    public function shop(string|Shop $shop, string|AccessToken|null $accessToken = null): self
    {
        $copy = clone $this;
        $copy->shop = Shop::from($shop);
        $copy->accessToken = match (true) {
            $accessToken instanceof AccessToken => $accessToken,
            is_string($accessToken) && trim($accessToken) !== '' => new AccessToken(trim($accessToken)),
            $accessToken === null => null,
            default => throw new \InvalidArgumentException('Shopify access token must not be empty.'),
        };

        return $copy;
    }

    /** @param array<string, mixed> $variables */
    public function variables(array $variables): self
    {
        $copy = clone $this;
        $copy->variables = $variables;

        return $copy;
    }

    /** @param array<string, mixed> $variables */
    public function query(string $query, array $variables = []): GraphqlResponse
    {
        if ($variables !== []) {
            return $this->variables($variables)->execute($query);
        }

        return $this->execute($query);
    }

    /** @param array<string, mixed> $variables */
    public function mutation(string $mutation, array $variables = []): GraphqlResponse
    {
        if ($variables !== []) {
            return $this->variables($variables)->execute($mutation);
        }

        return $this->execute($mutation);
    }

    private function execute(string $document): GraphqlResponse
    {
        if (! $this->shop) {
            throw new \LogicException('Call shop() before executing a Shopify GraphQL operation.');
        }
        $token = $this->accessToken ?? $this->tokenFor($this->shop);
        if (! $token) {
            throw new MissingContractBinding("No Shopify token was found for {$this->shop->domain}.");
        }
        $response = $this->http->send(new HttpRequest(
            'POST',
            "https://{$this->shop->domain}/admin/api/".config('shopify.api_version').'/graphql.json',
            ['Accept' => 'application/json', 'X-Shopify-Access-Token' => $token->value],
            ['query' => $document, 'variables' => $this->variables],
            config('shopify.http.timeout'),
            config('shopify.http.retries'),
            config('shopify.http.retry_delay_ms'),
        ));
        if (! $response->successful()) {
            throw new ShopifyHttpException($response);
        }
        if ($this->throttled($response->json)) {
            throw new GraphqlThrottled($this->retryAfter($response->json));
        }

        return new GraphqlResponse($response->json, $response->status);
    }

    /** @param array<string, mixed> $body */
    private function throttled(array $body): bool
    {
        foreach ($body['errors'] ?? [] as $error) {
            if (($error['extensions']['code'] ?? null) === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $body */
    private function retryAfter(array $body): int
    {
        $cost = $body['extensions']['cost'] ?? [];
        $needed = max(0, (int) ($cost['requestedQueryCost'] ?? 0) - (int) ($cost['throttleStatus']['currentlyAvailable'] ?? 0));
        $rate = (int) ($cost['throttleStatus']['restoreRate'] ?? 0);

        return $rate > 0 ? max(1, (int) ceil($needed / $rate)) : 1;
    }

    private function tokenFor(Shop $shop): ?AccessToken
    {
        if (! $this->container->bound(TokenRepository::class)) {
            throw new MissingContractBinding('Bind '.TokenRepository::class.' or provide an access token with shop($shop, $accessToken).');
        }

        return $this->container->make(TokenRepository::class)->findFor($shop);
    }
}
