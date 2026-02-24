<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepository::class,
            EloquentProductRepository::class
        );
    }

    public function boot(): void
    {
        // Event::listen(ProductCreated::class, ProductCreatedListener::class);
    }
}
