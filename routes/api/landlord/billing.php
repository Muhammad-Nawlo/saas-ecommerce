<?php

declare(strict_types=1);

use App\Landlord\Billing\Infrastructure\Http\Controllers\BillingWebhookController;
use App\Landlord\Billing\Infrastructure\Http\Controllers\PlanController;
use App\Landlord\Billing\Infrastructure\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('plans')->group(function (): void {
    Route::get('/', [PlanController::class, 'index']);
    Route::post('/', [PlanController::class, 'store']);
    Route::post('{id}/activate', [PlanController::class, 'activate']);
    Route::post('{id}/deactivate', [PlanController::class, 'deactivate']);
});
Route::prefix('subscriptions')->group(function (): void {
    Route::post('subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('cancel', [SubscriptionController::class, 'cancel']);
    Route::get('{tenantId}', [SubscriptionController::class, 'show']);
});
Route::prefix('billing')->group(function (): void {
    Route::post('webhook', BillingWebhookController::class)->name('landlord.billing.webhook');
});
