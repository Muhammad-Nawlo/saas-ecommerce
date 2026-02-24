<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Customer\AccountController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\PasswordController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Customer Identity API (tenant-scoped, Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'api',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])
    ->prefix('customer')
    ->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:customer-register');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:customer-login');
        Route::post('forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:customer-forgot-password');

        Route::middleware('auth:customer')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [ProfileController::class, 'me']);
            Route::patch('profile', [ProfileController::class, 'update']);
            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::patch('addresses/{id}', [AddressController::class, 'update']);
            Route::delete('addresses/{id}', [AddressController::class, 'destroy']);
            Route::post('password/change', [AccountController::class, 'changePassword']);
            Route::get('export', [AccountController::class, 'export']);
            Route::delete('account', [AccountController::class, 'destroy']);
        });

        Route::post('reset-password', [PasswordController::class, 'reset'])
            ->middleware('throttle:customer-reset-password');
    });
