<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Services;

use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopifyApiClient;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Builds {@see GraphQlClient} instances from configuration.
 */
final readonly class ShopifyClientFactory implements ClientFactory
{
    public function __construct(
        private HttpFactory $http,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function make(ShopModel $store): ShopifyApiClient
    {
        return $this->default()->forStore($store);
    }

    public function default(): ShopifyApiClient
    {
        /** @var array<string, mixed> $api */
        $api = $this->config->get('shopify.api', []);
        $api['api_version'] = $this->config->get('shopify.api_version');

        return new GraphQlClient($this->http, $api, $this->logger);
    }
}
