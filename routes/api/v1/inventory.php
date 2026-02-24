<?php

declare(strict_types=1);

use App\Modules\Inventory\Http\Api\Controllers\StockController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('inventory')
    ->group(function (): void {
        Route::post('/', [StockController::class, 'store']);
        Route::get('{productId}', [StockController::class, 'show']);
        Route::post('{productId}/increase', [StockController::class, 'increase']);
        Route::post('{productId}/decrease', [StockController::class, 'decrease']);
        Route::post('{productId}/reserve', [StockController::class, 'reserve']);
        Route::post('{productId}/release', [StockController::class, 'release']);
        Route::patch('{productId}/threshold', [StockController::class, 'setLowStockThreshold']);
    });
