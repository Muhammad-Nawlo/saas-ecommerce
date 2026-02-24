<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Api\Controllers\OrderController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Orders API (tenant). All routes require authentication.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    'auth:sanctum',
    'throttle:api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('orders')
    ->group(function (): void {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{orderId}', [OrderController::class, 'show']);
        Route::post('{orderId}/items', [OrderController::class, 'addItem']);
        Route::post('{orderId}/confirm', [OrderController::class, 'confirm']);
        Route::post('{orderId}/pay', [OrderController::class, 'pay']);
        Route::post('{orderId}/ship', [OrderController::class, 'ship']);
        Route::post('{orderId}/cancel', [OrderController::class, 'cancel']);
    });
