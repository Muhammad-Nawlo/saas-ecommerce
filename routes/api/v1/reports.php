<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ReportsController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    'throttle:api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('reports')
    ->group(function (): void {
        Route::get('revenue', [ReportsController::class, 'revenue']);
        Route::get('tax', [ReportsController::class, 'tax']);
        Route::get('products', [ReportsController::class, 'products']);
        Route::get('conversion', [ReportsController::class, 'conversion']);
    });
