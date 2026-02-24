<?php

declare(strict_types=1);

use App\Modules\Checkout\Infrastructure\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Checkout API (tenant). All write operations require authentication.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    'auth:sanctum',
    'throttle:checkout',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('checkout')
    ->group(function (): void {
        Route::post('/', [CheckoutController::class, 'checkout']);
        Route::post('/confirm-payment', [CheckoutController::class, 'confirmPayment'])->middleware('throttle:payment-confirm');
    });
