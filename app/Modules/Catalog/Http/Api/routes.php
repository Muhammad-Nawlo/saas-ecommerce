<?php

use App\Modules\Catalog\Http\Api\Controllers\ProductController;

Route::get('products', [ProductController::class, 'index']);
Route::post('products', [ProductController::class, 'store']);
