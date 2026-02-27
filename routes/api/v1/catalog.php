<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Api\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Catalog API (tenant). Read-only product listing public; writes require auth.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('catalog')
    ->group(function (): void {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{id}/price', [ProductController::class, 'updatePrice']);
            Route::post('products/{id}/activate', [ProductController::class, 'activate']);
            Route::post('products/{id}/deactivate', [ProductController::class, 'deactivate']);
        });
    });
