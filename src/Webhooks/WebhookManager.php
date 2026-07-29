<?php

namespace Decoupled\Shopify\Webhooks;

use Decoupled\Shopify\Contracts\WebhookRepository;
use Decoupled\Shopify\GraphQL\GraphqlClient;
use Decoupled\Shopify\GraphQL\GraphqlResponse;
use Decoupled\Shopify\Support\Shop;

class WebhookManager
{
    public function __construct(private readonly GraphqlClient $graph, private readonly WebhookRepository $definitions) {}

    public function register(string|Shop $shop, WebhookSubscription $webhook): GraphqlResponse
    {
        return $this->graph->shop($shop)->variables([
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

    public function delete(string|Shop $shop, string $id): GraphqlResponse
    {
        return $this->graph->shop($shop)->variables(['id' => $id])->mutation(<<<'GRAPHQL'
            mutation DeleteWebhook($id: ID!) {
              webhookSubscriptionDelete(id: $id) {
                deletedWebhookSubscriptionId
                userErrors { field message }
              }
            }
            GRAPHQL);
    }

    /** @return list<GraphqlResponse> */
    public function sync(string|Shop $shop, bool $deleteStale = false): array
    {
        $shop = Shop::from($shop);
        $responses = [];
        $remote = $this->list($shop)->data()['webhookSubscriptions']['edges'] ?? [];
        $existing = [];
        foreach ($remote as $edge) {
            $node = $edge['node'];
            $existing[$node['topic'].'|'.$node['callbackUrl']] = $node['id'];
        }
        foreach ($this->definitions->desiredFor($shop) as $webhook) {
            $key = $this->topic($webhook->topic).'|'.$webhook->callbackUrl;
            if (! isset($existing[$key])) {
                $responses[] = $this->register($shop, $webhook);
            }
            unset($existing[$key]);
        }
        if ($deleteStale) {
            foreach ($existing as $id) {
                $responses[] = $this->delete($shop, $id);
            }
        }

        return $responses;
    }

    public function list(string|Shop $shop): GraphqlResponse
    {
        return $this->graph->shop($shop)->query(<<<'GRAPHQL'
            query ListWebhooks {
              webhookSubscriptions(first: 250) {
                edges { node { id topic callbackUrl } }
              }
            }
            GRAPHQL);
    }

    private function topic(string $topic): string
    {
        return strtoupper(str_replace('/', '_', $topic));
    }
}
