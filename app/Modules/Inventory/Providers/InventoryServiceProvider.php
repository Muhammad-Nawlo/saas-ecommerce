<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Domain\Repositories\StockItemRepository;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentStockItemRepository;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StockItemRepository::class,
            EloquentStockItemRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
