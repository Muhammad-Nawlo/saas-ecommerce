<?php

namespace App\Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Route::middleware([
            'api',
            InitializeTenancyBySubdomain::class,
            PreventAccessFromCentralDomains::class,
        ])
            ->prefix('v1/catalog')
            ->group(__DIR__ . '/../Http/Api/routes.php');
    }
}
