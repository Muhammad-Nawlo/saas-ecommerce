<?php

declare(strict_types=1);

use App\Http\Controllers\InvoicePdfDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('invoices/{invoice}/pdf', InvoicePdfDownloadController::class)->name('tenant.invoice.pdf');
});
