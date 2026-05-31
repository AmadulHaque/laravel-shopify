<?php

declare(strict_types=1);

use AmadulHaque\LaravelShopify\Http\Controllers\CallbackController;
use AmadulHaque\LaravelShopify\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('shopify.routes.prefix'))
    ->middleware((array) config('shopify.routes.middleware', ['web']))
    ->group(function (): void {
        Route::get((string) config('shopify.routes.install'), InstallController::class)
            ->middleware('shopify.oauth')
            ->name('shopify.install');

        Route::get((string) config('shopify.routes.callback'), CallbackController::class)
            ->middleware('shopify.oauth')
            ->name('shopify.callback');
    });
