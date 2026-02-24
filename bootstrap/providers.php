<?php

use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    \App\Providers\Filament\LandlordPanelProvider::class,
    \App\Providers\Filament\TenantPanelProvider::class,
    App\Providers\TenancyServiceProvider::class,


    //SAAS Providers
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
];
