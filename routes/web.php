<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class);
Route::get('/health', HealthController::class)->name('health');
