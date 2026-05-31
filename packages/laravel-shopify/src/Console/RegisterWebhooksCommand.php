<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Console;

use AmadulHaque\LaravelShopify\Actions\RegisterWebhooks;
use AmadulHaque\LaravelShopify\Contracts\ShopModel;
use AmadulHaque\LaravelShopify\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Registers the configured webhook subscriptions with Shopify for one or all stores.
 */
final class RegisterWebhooksCommand extends Command
{
    protected $signature = 'shopify:webhook:register
        {--shop= : Limit registration to a single shop domain}';

    protected $description = 'Register configured webhook subscriptions with Shopify.';

    public function handle(RegisterWebhooks $registerWebhooks): int
    {
        $stores = $this->stores();

        if ($stores->isEmpty()) {
            $this->components->warn('No active stores found to register webhooks for.');

            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            $this->components->info("Registering webhooks for {$store->getShopDomain()}");

            $rows = [];

            foreach ($registerWebhooks($store) as $topic => $result) {
                $rows[] = [
                    $topic,
                    $result['success'] ? '<fg=green>ok</>' : '<fg=red>failed</>',
                    $result['errors'] === [] ? '—' : implode('; ', $result['errors']),
                ];
            }

            $this->table(['Topic', 'Status', 'Errors'], $rows);
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ShopModel>
     */
    private function stores()
    {
        /** @var class-string<Model&ShopModel> $model */
        $model = config('shopify.store.model', Store::class);

        $query = $model::query()->whereNull('uninstalled_at');

        if ($shop = $this->option('shop')) {
            $query->where('shop_domain', $shop);
        }

        return $query->get();
    }
}
