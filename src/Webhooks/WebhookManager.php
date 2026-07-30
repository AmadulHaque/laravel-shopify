<?php

namespace Amadulhaque\Shopify\Webhooks;

use Amadulhaque\Shopify\Contracts\WebhookRepository;
use Amadulhaque\Shopify\GraphQL\GraphqlClient;
use Amadulhaque\Shopify\GraphQL\GraphqlResponse;
use Amadulhaque\Shopify\Support\AccessToken;
use Amadulhaque\Shopify\Support\Shop;

class WebhookManager
{
    public function __construct(private readonly GraphqlClient $graph, private readonly WebhookRepository $definitions) {}

    public function register(string|Shop $shop, WebhookSubscription $webhook, string|AccessToken|null $accessToken = null): GraphqlResponse
    {
        return $this->graph->shop($shop, $accessToken)->variables([
            'topic' => $this->topic($webhook->topic),
            'uri' => $webhook->callbackUrl,
        ])->mutation(<<<'GRAPHQL'
            mutation RegisterWebhook($topic: WebhookSubscriptionTopic!, $uri: URL!) {
              webhookSubscriptionCreate(topic: $topic, webhookSubscription: {callbackUrl: $uri}) {
                webhookSubscription { id topic callbackUrl }
                userErrors { field message }
              }
            }
            GRAPHQL);
    }

    public function delete(string|Shop $shop, string $id, string|AccessToken|null $accessToken = null): GraphqlResponse
    {
        return $this->graph->shop($shop, $accessToken)->variables(['id' => $id])->mutation(<<<'GRAPHQL'
            mutation DeleteWebhook($id: ID!) {
              webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors { field message }
              }
            }
            GRAPHQL);
    }

    /** @return list<GraphqlResponse> */
    public function sync(string|Shop $shop, bool $deleteStale = false, string|AccessToken|null $accessToken = null): array
    {
        $shop = Shop::from($shop);
        $responses = [];
        $remote = $this->all($shop, $accessToken);
        $existing = [];
        foreach ($remote as $node) {
            $existing[$node['topic'].'|'.$node['callbackUrl']] = $node['id'];
        }
        foreach ($this->definitions->desiredFor($shop) as $webhook) {
            $key = $this->topic($webhook->topic).'|'.$webhook->callbackUrl;
            if (! isset($existing[$key])) {
                $responses[] = $this->register($shop, $webhook, $accessToken);
            }
            unset($existing[$key]);
        }
        if ($deleteStale) {
            foreach ($existing as $id) {
                $responses[] = $this->delete($shop, $id, $accessToken);
            }
        }

        return $responses;
    }

    public function list(string|Shop $shop, ?string $after = null, string|AccessToken|null $accessToken = null): GraphqlResponse
    {
        return $this->graph->shop($shop, $accessToken)->query(<<<'GRAPHQL'
            query ListWebhooks($after: String) {
              webhookSubscriptions(first: 250, after: $after) {
                pageInfo { hasNextPage endCursor }
                edges { cursor node { id topic callbackUrl } }
              }
            }
            GRAPHQL, ['after' => $after]);
    }

    /** @return list<array{id: string, topic: string, callbackUrl: string}> */
    public function all(string|Shop $shop, string|AccessToken|null $accessToken = null): array
    {
        $subscriptions = [];
        $after = null;
        do {
            $connection = $this->list($shop, $after, $accessToken)->data()['webhookSubscriptions'] ?? [];
            foreach ($connection['edges'] ?? [] as $edge) {
                if (isset($edge['node']['id'], $edge['node']['topic'], $edge['node']['callbackUrl'])) {
                    $subscriptions[] = $edge['node'];
                }
            }
            $pageInfo = $connection['pageInfo'] ?? [];
            $after = ($pageInfo['hasNextPage'] ?? false) ? ($pageInfo['endCursor'] ?? null) : null;
        } while ($after !== null);

        return $subscriptions;
    }

    private function topic(string $topic): string
    {
        return strtoupper(str_replace('/', '_', $topic));
    }
}
