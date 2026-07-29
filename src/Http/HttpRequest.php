<?php

namespace Decoupled\Shopify\Http;

final readonly class HttpRequest
{
    /** @param array<string, string> $headers @param array<string, mixed> $json */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public array $json = [],
        public int $timeout = 30,
        public int $retries = 0,
        public int $retryDelayMs = 0,
    ) {}
}
