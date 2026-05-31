<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use Illuminate\Http\Client\Response;

/**
 * A typed wrapper around a Shopify REST Admin API response (fallback path).
 */
final readonly class RestResponse
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public array $body,
        public int $status,
        public array $headers,
    ) {}

    public static function fromHttp(Response $response): self
    {
        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return new self(
            body: $json,
            status: $response->status(),
            headers: $response->headers(),
        );
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function throttled(): bool
    {
        return $this->status === 429;
    }

    /**
     * The Retry-After delay (in seconds) advertised by the REST leaky bucket, if any.
     */
    public function retryAfter(): ?float
    {
        $value = $this->headers['Retry-After'][0] ?? null;

        return $value !== null ? (float) $value : null;
    }
}
