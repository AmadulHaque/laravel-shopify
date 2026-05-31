<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Services;

use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\DTOs\GraphQlResponse;
use AmadulHaque\LaravelShopify\DTOs\RestResponse;
use AmadulHaque\LaravelShopify\DTOs\ThrottleStatus;
use AmadulHaque\LaravelShopify\Exceptions\ShopifyApiException;
use AmadulHaque\LaravelShopify\Exceptions\ThrottledException;
use AmadulHaque\LaravelShopify\Support\ThrottleBackoff;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;
use Psr\Log\LoggerInterface;

/**
 * GraphQL-first Shopify Admin API client with rate-limit-aware retries.
 *
 * GraphQL uses a calculated-cost leaky bucket: a THROTTLED error (or a low
 * remaining bucket) triggers a backoff computed from the bucket's restore rate.
 * REST falls back to the classic request-based bucket via 429 + Retry-After.
 *
 * The client is immutable per store — {@see self::forStore()} returns a clone —
 * so the shared singleton never leaks one tenant's token into another's request.
 */
final class GraphQlClient implements ShopifyApiClient
{
    private ?ShopModel $store = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function forStore(ShopModel $store): static
    {
        $clone = clone $this;
        $clone->store = $store;

        return $clone;
    }

    public function graphql(string $query, array $variables = []): GraphQlResponse
    {
        $attempt = 0;

        while (true) {
            $response = $this->request()->post('graphql.json', [
                'query' => $query,
                'variables' => (object) $variables,
            ]);

            $this->guardTransport($response);

            $dto = GraphQlResponse::fromHttp($response);

            if (! $this->isThrottled($response, $dto)) {
                return $dto;
            }

            $this->backoffOrFail($attempt, $dto->cost?->requestedQueryCost ?? 0, $dto->cost);
            $attempt++;
        }
    }

    public function rest(string $method, string $endpoint, array $payload = []): RestResponse
    {
        $attempt = 0;

        while (true) {
            $response = $this->request()->send(
                strtoupper($method),
                ltrim($endpoint, '/'),
                $this->restOptions($method, $payload),
            );

            $dto = RestResponse::fromHttp($response);

            if (! $dto->throttled()) {
                $this->guardTransport($response);

                return $dto;
            }

            $this->backoffOrFail($attempt, 0, null, $dto->retryAfter());
            $attempt++;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function restOptions(string $method, array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        return strtoupper($method) === 'GET'
            ? ['query' => $payload]
            : ['json' => $payload];
    }

    private function request(): PendingRequest
    {
        $store = $this->store ?? throw ShopifyApiException::missingStore();

        $token = $store->getAccessToken()
            ?? throw ShopifyApiException::missingAccessToken($store->getShopDomain());

        /** @var array{timeout?: int, connect_timeout?: int} $http */
        $http = $this->config['http'] ?? [];

        return $this->http
            ->baseUrl("https://{$store->getShopDomain()}/admin/api/{$this->apiVersion()}/")
            ->withHeaders(['X-Shopify-Access-Token' => $token])
            ->acceptJson()
            ->timeout($http['timeout'] ?? 15)
            ->connectTimeout($http['connect_timeout'] ?? 5);
    }

    private function isThrottled(Response $response, GraphQlResponse $dto): bool
    {
        return $response->status() === 429 || $dto->throttled();
    }

    private function guardTransport(Response $response): void
    {
        // GraphQL returns 200 even for business errors; only treat genuine
        // non-throttle HTTP failures as transport exceptions.
        if ($response->status() >= 400 && $response->status() !== 429) {
            throw ShopifyApiException::requestFailed(
                $this->store?->getShopDomain() ?? 'unknown',
                $response->status(),
                $response->json() ?? [],
            );
        }
    }

    /**
     * Sleep before the next attempt, or throw once retries are exhausted.
     */
    private function backoffOrFail(
        int $attempt,
        int $requestedCost,
        ?ThrottleStatus $cost,
        ?float $retryAfterSeconds = null,
    ): void {
        /** @var array{max_retries?: int, base_delay_ms?: int, max_delay_ms?: int} $throttle */
        $throttle = $this->config['throttle'] ?? [];
        $maxRetries = $throttle['max_retries'] ?? 5;

        $domain = $this->store?->getShopDomain() ?? 'unknown';

        if ($attempt >= $maxRetries) {
            $this->logger->warning('Shopify request throttled; retries exhausted.', [
                'shop' => $domain,
                'attempts' => $attempt + 1,
            ]);

            throw ThrottledException::exhausted($domain, $attempt + 1, $cost);
        }

        $delayMs = $retryAfterSeconds !== null
            ? (int) ($retryAfterSeconds * 1000)
            : ThrottleBackoff::delayMs(
                attempt: $attempt,
                baseMs: $throttle['base_delay_ms'] ?? 500,
                maxMs: $throttle['max_delay_ms'] ?? 10_000,
                status: $cost,
                requestedCost: $requestedCost,
            );

        $this->logger->info('Shopify request throttled; backing off.', [
            'shop' => $domain,
            'attempt' => $attempt + 1,
            'delay_ms' => $delayMs,
        ]);

        Sleep::for($delayMs)->milliseconds();
    }

    private function apiVersion(): string
    {
        return (string) ($this->config['api_version'] ?? '2025-07');
    }
}
