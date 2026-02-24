<?php

namespace App\Providers;

use App\Landlord\Models\Plan;
use App\Landlord\Models\Subscription;
use App\Landlord\Models\Tenant;
use App\Landlord\Models\Feature;
use App\Landlord\Policies\PlanPolicy;
use App\Landlord\Policies\SubscriptionPolicy;
use App\Landlord\Policies\TenantPolicy;
use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;
use App\Modules\Orders\Infrastructure\Persistence\CustomerSummaryModel;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Models\User;
use App\Observers\FeatureAuditObserver;
use App\Observers\InventoryAuditObserver;
use App\Observers\OrderAuditObserver;
use App\Observers\PlanAuditObserver;
use App\Observers\ProductAuditObserver;
use App\Observers\SubscriptionAuditObserver;
use App\Observers\TenantAuditObserver;
use App\Observers\UserAuditObserver;
use App\Models\Customer\Customer;
use App\Models\Invoice\Invoice;
use App\Policies\CustomerIdentityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('customer-register', fn () => Limit::perMinute(5)->by(request()->ip()));
        RateLimiter::for('customer-login', fn () => Limit::perMinute(5)->by(request()->ip()));
        RateLimiter::for('customer-forgot-password', fn () => Limit::perMinute(3)->by(request()->ip()));
        RateLimiter::for('customer-reset-password', fn () => Limit::perMinute(3)->by(request()->ip()));
        Gate::policy(ProductModel::class, ProductPolicy::class);
        Gate::policy(OrderModel::class, OrderPolicy::class);
        Gate::policy(CustomerSummaryModel::class, CustomerPolicy::class);
        Gate::policy(Customer::class, CustomerIdentityPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(StockItemModel::class, InventoryPolicy::class);

        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        ProductModel::observe(ProductAuditObserver::class);
        OrderModel::observe(OrderAuditObserver::class);
        StockItemModel::observe(InventoryAuditObserver::class);
        User::observe(UserAuditObserver::class);

        Plan::observe(PlanAuditObserver::class);
        Feature::observe(FeatureAuditObserver::class);
        Subscription::observe(SubscriptionAuditObserver::class);
        Tenant::observe(TenantAuditObserver::class);
    }
}
