<?php

use App\Modules\Auth\Http\Api\Controllers\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('v1')->group(function () {
    Route::post('/auth/login', [LoginController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [LoginController::class, 'logout']);
        Route::get('/auth/me', function (Request $request) {
            return response()->json($request->user());
        });
    });
});
