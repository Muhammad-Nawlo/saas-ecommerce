<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ReportsController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Reports API (tenant). All routes require authentication.
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    'auth:sanctum',
    'throttle:api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('reports')
    ->group(function (): void {
        Route::get('revenue', [ReportsController::class, 'revenue']);
        Route::get('tax', [ReportsController::class, 'tax']);
        Route::get('products', [ReportsController::class, 'products']);
        Route::get('conversion', [ReportsController::class, 'conversion']);
    });
