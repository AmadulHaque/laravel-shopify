<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\Billing\Plan;
use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\DTOs\SubscriptionConfirmation;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Creates an app subscription for a store via the GraphQL Billing API.
 *
 * Builds a recurring line item and, when the plan defines usage pricing, an
 * additional usage line item. Returns the confirmation URL the merchant must
 * approve before the subscription becomes active.
 *
 * @see https://shopify.dev/docs/api/admin-graphql/latest/mutations/appSubscriptionCreate
 */
final readonly class CreateSubscription
{
    private const MUTATION = <<<'GRAPHQL'
    mutation appSubscriptionCreate($name: String!, $returnUrl: URL!, $test: Boolean, $trialDays: Int, $lineItems: [AppSubscriptionLineItemInput!]!) {
        appSubscriptionCreate(name: $name, returnUrl: $returnUrl, test: $test, trialDays: $trialDays, lineItems: $lineItems) {
            appSubscription { id status }
            confirmationUrl
            userErrors { field message }
        }
    }
    GRAPHQL;

    public function __construct(
        private ClientFactory $clients,
        private Config $config,
    ) {}

    public function __invoke(ShopModel $store, Plan $plan): SubscriptionConfirmation
    {
        $response = $this->clients->make($store)->graphql(self::MUTATION, [
            'name' => $plan->name,
            'returnUrl' => $this->returnUrl($store),
            'test' => (bool) $this->config->get('shopify.billing.test', true),
            'trialDays' => $plan->trialDays,
            'lineItems' => $this->lineItems($plan),
        ]);

        $result = $response->data['appSubscriptionCreate'] ?? [];
        $userErrors = $result['userErrors'] ?? [];

        if ($response->hasErrors() || $userErrors !== [] || empty($result['confirmationUrl'])) {
            throw BillingException::subscribeFailed($plan->name, $this->messages($response->errors, $userErrors));
        }

        return new SubscriptionConfirmation(
            confirmationUrl: (string) $result['confirmationUrl'],
            subscriptionId: $result['appSubscription']['id'] ?? null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItems(Plan $plan): array
    {
        $lineItems = [[
            'plan' => [
                'appRecurringPricingDetails' => [
                    'price' => ['amount' => $plan->price, 'currencyCode' => $plan->currency],
                    'interval' => $plan->interval,
                ],
            ],
        ]];

        if ($plan->hasUsagePricing()) {
            $lineItems[] = [
                'plan' => [
                    'appUsagePricingDetails' => [
                        'cappedAmount' => ['amount' => $plan->usage['capped_amount'], 'currencyCode' => $plan->currency],
                        'terms' => $plan->usage['terms'],
                    ],
                ],
            ];
        }

        return $lineItems;
    }

    private function returnUrl(ShopModel $store): string
    {
        $base = rtrim((string) $this->config->get('shopify.app_url'), '/');
        $prefix = trim((string) $this->config->get('shopify.billing.routes.prefix'), '/');
        $confirm = trim((string) $this->config->get('shopify.billing.routes.confirm'), '/');

        return "{$base}/{$prefix}/{$confirm}?shop={$store->getShopDomain()}";
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @param  list<array<string, mixed>>  $userErrors
     * @return list<string>
     */
    private function messages(array $errors, array $userErrors): array
    {
        $messages = [];

        foreach ([...$errors, ...$userErrors] as $error) {
            $messages[] = (string) ($error['message'] ?? 'Unknown billing error.');
        }

        return $messages;
    }
}
