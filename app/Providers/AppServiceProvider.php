<?php

namespace App\Providers;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Orders\Infrastructure\Persistence\CustomerSummaryModel;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ProductModel::class, ProductPolicy::class);
        Gate::policy(OrderModel::class, OrderPolicy::class);
        Gate::policy(CustomerSummaryModel::class, CustomerPolicy::class);
    }
}
