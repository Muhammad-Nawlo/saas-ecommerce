<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| API Documentation (Swagger UI) at /api/documentation
|--------------------------------------------------------------------------
*/
Route::get('documentation', function () {
    if (Config::get('l5-swagger.generateAlways')) {
        \Darkaonline\L5Swagger\Generator::generateDocs();
    }
    return Response::make(
        View::make('l5-swagger::index', [
            'apiKey' => Config::get('l5-swagger.api-key'),
            'apiKeyVar' => Config::get('l5-swagger.api-key-var'),
            'apiKeyInject' => Config::get('l5-swagger.api-key-inject'),
            'secure' => Request::secure(),
            'urlToDocs' => url(Config::get('l5-swagger.doc-route')) . '/api-docs.json',
            'requestHeaders' => Config::get('l5-swagger.requestHeaders', []),
        ]),
        200
    );
})->name('api.documentation');

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
