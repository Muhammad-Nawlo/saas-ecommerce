<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    require __DIR__ . '/api/v1/catalog.php';
    require __DIR__ . '/api/v1/checkout.php';
    require __DIR__ . '/api/v1/inventory.php';
    require __DIR__ . '/api/v1/orders.php';
    require __DIR__ . '/api/v1/payments.php';
    require __DIR__ . '/api/v1/cart.php';
});
