<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Api\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('payments')
    ->group(function (): void {
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('order/{orderId}', [PaymentController::class, 'indexByOrder']);
        Route::post('{paymentId}/confirm', [PaymentController::class, 'confirm']);
        Route::post('{paymentId}/refund', [PaymentController::class, 'refund']);
        Route::post('{paymentId}/cancel', [PaymentController::class, 'cancel']);
    });
