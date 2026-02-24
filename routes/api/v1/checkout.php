<?php

declare(strict_types=1);

use App\Modules\Checkout\Infrastructure\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    'throttle:api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('checkout')
    ->group(function (): void {
        Route::post('/', [CheckoutController::class, 'checkout']);
        Route::post('/confirm-payment', [CheckoutController::class, 'confirmPayment']);
    });
