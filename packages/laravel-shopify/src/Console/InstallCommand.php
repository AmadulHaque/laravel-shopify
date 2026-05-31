<?php

declare(strict_types=1);

namespace AmadulHaque\LaravelShopify\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

/**
 * Publishes the package's config + migrations and prints setup guidance.
 */
final class InstallCommand extends Command
{
    protected $signature = 'shopify:install
        {--force : Overwrite any existing published files}';

    protected $description = 'Publish Shopify config and migrations, then print setup instructions.';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'shopify-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->callSilently('vendor:publish', [
            '--tag' => 'shopify-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        info('Shopify config and migrations published.');

        $callback = rtrim((string) config('shopify.app_url'), '/')
            .'/'.trim((string) config('shopify.routes.prefix'), '/')
            .'/'.trim((string) config('shopify.routes.callback'), '/');

        note(implode(PHP_EOL, [
            'Next steps:',
            '  1. Set SHOPIFY_API_KEY, SHOPIFY_API_SECRET and SHOPIFY_SCOPES in your .env',
            '  2. In your Shopify Partner dashboard, set the allowed redirection URL to:',
            "       {$callback}",
            '  3. Run "php artisan migrate" to create the shopify_stores table',
            '  4. Visit /'.trim((string) config('shopify.routes.prefix'), '/').'/install?shop=your-store.myshopify.com to begin OAuth',
        ]));

        return self::SUCCESS;
    }
}
