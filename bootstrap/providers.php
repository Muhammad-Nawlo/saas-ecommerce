<?php

use App\Landlord\Billing\Providers\BillingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Checkout\Providers\CheckoutServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    \App\Providers\Filament\LandlordPanelProvider::class,
    \App\Providers\Filament\TenantPanelProvider::class,
    App\Providers\TenancyServiceProvider::class,


    // Landlord
    BillingServiceProvider::class,

    //SAAS Providers
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    CheckoutServiceProvider::class,
    InventoryServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
];
