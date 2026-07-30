<?php

namespace Amadulhaque\Shopify\Http;

final readonly class HttpResponse
{
    /** @param array<string, mixed> $json @param array<string, list<string>> $headers */
    public function __construct(
        public int $status,
        public array $json,
        public array $headers = [],
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
