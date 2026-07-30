<?php

namespace Amadulhaque\Shopify\GraphQL;

final readonly class GraphqlResponse
{
    /** @param array<string, mixed> $body */
    public function __construct(public array $body, public int $status) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->body['data'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function errors(): array
    {
        return $this->body['errors'] ?? [];
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }
}
