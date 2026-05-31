<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Http\Controllers\BillingConfirmController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('shopify.billing.routes.prefix'))
    ->middleware((array) config('shopify.billing.routes.middleware', ['web']))
    ->group(function (): void {
        Route::get((string) config('shopify.billing.routes.confirm'), BillingConfirmController::class)
            ->name('shopify.billing.confirm');
    });
