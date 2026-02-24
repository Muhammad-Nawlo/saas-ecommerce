<?php

declare(strict_types=1);

namespace App\Modules\Orders\Providers;

use App\Modules\Orders\Application\Services\InventoryService;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Infrastructure\Persistence\EloquentOrderRepository;
use App\Modules\Orders\Infrastructure\Services\LaravelInventoryService;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OrderRepository::class,
            EloquentOrderRepository::class
        );
        $this->app->bind(
            InventoryService::class,
            LaravelInventoryService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
