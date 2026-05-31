<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\DTOs;

use Illuminate\Http\Client\Response;

/**
 * A typed wrapper around a Shopify GraphQL Admin API response.
 *
 * Transport/throttle failures are raised as exceptions by the client; this DTO
 * carries successful payloads along with any GraphQL-level `errors`, leaving
 * the caller to decide how to handle business-level failures.
 */
final readonly class GraphQlResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public array $data,
        public array $errors,
        public ?ThrottleStatus $cost,
        public int $status,
    ) {}

    public static function fromHttp(Response $response): self
    {
        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        $extensions = is_array($json['extensions'] ?? null) ? $json['extensions'] : [];

        return new self(
            data: is_array($json['data'] ?? null) ? $json['data'] : [],
            errors: is_array($json['errors'] ?? null) ? array_values($json['errors']) : [],
            cost: ThrottleStatus::fromExtensions($extensions),
            status: $response->status(),
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Whether any GraphQL error carries the THROTTLED extension code.
     */
    public function throttled(): bool
    {
        foreach ($this->errors as $error) {
            if (($error['extensions']['code'] ?? null) === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }
}
