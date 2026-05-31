<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post((string) config('shopify.webhooks.prefix'), WebhookController::class)
    ->middleware(array_merge(
        (array) config('shopify.webhooks.middleware', ['api']),
        ['shopify.webhook'],
    ))
    ->name('shopify.webhooks');
