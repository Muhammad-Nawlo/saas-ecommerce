<?php

use App\Landlord\Billing\Providers\BillingServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Checkout\Providers\CheckoutServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Orders\Providers\OrdersServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    BillingServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    CheckoutServiceProvider::class,
    InventoryServiceProvider::class,
    OrdersServiceProvider::class,
    PaymentsServiceProvider::class,
];

// Skip Filament in testing to avoid UI layer coupling with backend/domain tests.
if ((getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')) !== 'testing') {
    $providers[] = \App\Providers\Filament\LandlordPanelProvider::class;
    $providers[] = \App\Providers\Filament\TenantPanelProvider::class;
}

return $providers;
