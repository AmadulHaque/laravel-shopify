<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Contracts\WebhookRegistry;
use AmadulHaque\LaravelShopify\Support\WebhookTopic;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Registers (or re-registers) the configured webhook subscriptions for a store
 * via the GraphQL Admin API, pointing every topic at this app's callback URL.
 *
 * @see https://shopify.dev/docs/api/admin-graphql/latest/mutations/webhookSubscriptionCreate
 */
final readonly class RegisterWebhooks
{
    private const MUTATION = <<<'GRAPHQL'
    mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
        webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
            webhookSubscription { id }
            userErrors { field message }
        }
    }
    GRAPHQL;

    public function __construct(
        private ClientFactory $clients,
        private WebhookRegistry $registry,
        private Config $config,
    ) {}

    /**
     * @return array<string, array{success: bool, id: ?string, errors: list<string>}>
     */
    public function __invoke(ShopModel $store): array
    {
        $client = $this->clients->make($store);
        $callbackUrl = $this->callbackUrl();
        $results = [];

        foreach ($this->registry->topics() as $topic) {
            $response = $client->graphql(self::MUTATION, [
                'topic' => WebhookTopic::toEnum($topic),
                'webhookSubscription' => [
                    'callbackUrl' => $callbackUrl,
                    'format' => 'JSON',
                ],
            ]);

            $result = $response->data['webhookSubscriptionCreate'] ?? [];
            $userErrors = $result['userErrors'] ?? [];

            $results[$topic] = [
                'success' => $userErrors === [] && ! $response->hasErrors(),
                'id' => $result['webhookSubscription']['id'] ?? null,
                'errors' => $this->collectErrors($response->errors, $userErrors),
            ];
        }

        return $results;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @param  list<array<string, mixed>>  $userErrors
     * @return list<string>
     */
    private function collectErrors(array $errors, array $userErrors): array
    {
        $messages = [];

        foreach ($errors as $error) {
            $messages[] = (string) ($error['message'] ?? 'Unknown GraphQL error.');
        }

        foreach ($userErrors as $error) {
            $messages[] = (string) ($error['message'] ?? 'Unknown user error.');
        }

        return $messages;
    }

    private function callbackUrl(): string
    {
        $base = rtrim((string) $this->config->get('shopify.app_url'), '/');
        $prefix = trim((string) $this->config->get('shopify.webhooks.prefix'), '/');

        return "{$base}/{$prefix}";
    }
}
