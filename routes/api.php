<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [UserController::class, 'show'])->middleware('auth:sanctum');

Route::prefix('landlord')->group(function (): void {
    require __DIR__ . '/api/landlord/billing.php';
});

Route::prefix('v1')->group(function (): void {
    require __DIR__ . '/api/v1/catalog.php';
    require __DIR__ . '/api/v1/checkout.php';
    require __DIR__ . '/api/v1/customer.php';
    require __DIR__ . '/api/v1/inventory.php';
    require __DIR__ . '/api/v1/orders.php';
    require __DIR__ . '/api/v1/payments.php';
    require __DIR__ . '/api/v1/cart.php';
    require __DIR__ . '/api/v1/reports.php';
});
