<?php

declare(strict_types=1);

namespace App\Modules\Cart\Providers;

use App\Modules\Cart\Application\Services\OrderCreationService;
use App\Modules\Cart\Application\Services\StockValidationService;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Infrastructure\Persistence\EloquentCartRepository;
use App\Modules\Cart\Infrastructure\Services\CartOrderCreationService;
use App\Modules\Cart\Infrastructure\Services\CartStockValidationService;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CartRepository::class,
            EloquentCartRepository::class
        );
        $this->app->bind(
            StockValidationService::class,
            CartStockValidationService::class
        );
        $this->app->bind(
            OrderCreationService::class,
            CartOrderCreationService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
