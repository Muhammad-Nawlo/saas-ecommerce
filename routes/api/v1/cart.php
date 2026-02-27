<?php

declare(strict_types=1);

use App\Modules\Cart\Http\Api\Controllers\CartController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Cart API (tenant). All routes require authentication.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    'auth:sanctum',
    'throttle:api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('cart')
    ->group(function (): void {
        Route::post('/', [CartController::class, 'store']);
        Route::get('{cartId}', [CartController::class, 'show']);
        Route::post('{cartId}/items', [CartController::class, 'addItem']);
        Route::put('{cartId}/items/{productId}', [CartController::class, 'updateItem']);
        Route::delete('{cartId}/items/{productId}', [CartController::class, 'removeItem']);
        Route::post('{cartId}/clear', [CartController::class, 'clear']);
        Route::post('{cartId}/convert', [CartController::class, 'convert']);
        Route::post('{cartId}/abandon', [CartController::class, 'abandon']);
    });
