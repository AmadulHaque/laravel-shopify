<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Actions;

use AmadulHaque\LaravelShopify\Contracts\ClientFactory;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\DTOs\UsageRecord;
use AmadulHaque\LaravelShopify\Exceptions\BillingException;

/**
 * Records a usage charge against a subscription's usage line item.
 *
 * @see https://shopify.dev/docs/api/admin-graphql/latest/mutations/appUsageRecordCreate
 */
final readonly class RecordUsage
{
    private const MUTATION = <<<'GRAPHQL'
    mutation appUsageRecordCreate($subscriptionLineItemId: ID!, $price: MoneyInput!, $description: String!) {
        appUsageRecordCreate(subscriptionLineItemId: $subscriptionLineItemId, price: $price, description: $description) {
            appUsageRecord { id }
            userErrors { field message }
        }
    }
    GRAPHQL;

    public function __construct(private ClientFactory $clients) {}

    public function __invoke(ShopModel $store, string $usageLineItemId, string $currency, string $description, float $amount): UsageRecord
    {
        $response = $this->clients->make($store)->graphql(self::MUTATION, [
            'subscriptionLineItemId' => $usageLineItemId,
            'price' => ['amount' => $amount, 'currencyCode' => $currency],
            'description' => $description,
        ]);

        $result = $response->data['appUsageRecordCreate'] ?? [];
        $userErrors = $result['userErrors'] ?? [];

        if ($response->hasErrors() || $userErrors !== [] || empty($result['appUsageRecord']['id'])) {
            throw BillingException::usageRecordFailed($this->messages($response->errors, $userErrors));
        }

        return new UsageRecord(
            id: (string) $result['appUsageRecord']['id'],
            description: $description,
            amount: $amount,
        );
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
