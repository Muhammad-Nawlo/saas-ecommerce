<?php

declare(strict_types=1);

use App\Landlord\Billing\Infrastructure\Http\Controllers\PlanController;
use App\Landlord\Billing\Infrastructure\Http\Controllers\SubscriptionController;
use App\Landlord\Http\Controllers\BillingCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landlord SaaS API. Subscription/plans/billing require auth; webhook public.
|--------------------------------------------------------------------------
*/

Route::prefix('plans')->group(function (): void {
    Route::get('/', [PlanController::class, 'index']);
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('/', [PlanController::class, 'store']);
        Route::post('{id}/activate', [PlanController::class, 'activate']);
        Route::post('{id}/deactivate', [PlanController::class, 'deactivate']);
    });
});

Route::prefix('subscriptions')->middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('cancel', [SubscriptionController::class, 'cancel']);
    Route::get('{tenantId}', [SubscriptionController::class, 'show']);
});

Route::prefix('billing')->group(function (): void {
    Route::post('checkout/{plan}', BillingCheckoutController::class)
        ->middleware(['auth:sanctum', 'throttle:api'])
        ->name('landlord.billing.checkout');
    Route::post('webhook', \App\Landlord\Http\Controllers\StripeWebhookController::class)
        ->middleware('throttle:webhook')
        ->name('landlord.billing.webhook');
    Route::get('success', fn () => response()->json(['message' => 'Checkout successful']))->name('landlord.billing.success');
    Route::get('cancel', fn () => response()->json(['message' => 'Checkout cancelled']))->name('landlord.billing.cancel');
    Route::get('portal/return', fn () => response()->json(['message' => 'Returned from billing portal']))->name('landlord.billing.portal.return');
});
