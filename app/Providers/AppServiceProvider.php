<?php

namespace App\Providers;

use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Messaging\LaravelEventBus;
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
use App\Models\Inventory\InventoryLocation;
use App\Models\Currency\Currency;
use App\Models\Invoice\Invoice;
use App\Policies\CurrencyPolicy;
use App\Policies\CustomerIdentityPolicy;
use App\Policies\InventoryLocationPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventBus::class, LaravelEventBus::class);

        $this->app->bind(
            \App\Contracts\Currency\RateProviderInterface::class,
            fn () => config('currency.rate_provider') === 'api'
                ? new \App\Services\Currency\ApiRateProvider()
                : new \App\Services\Currency\ManualRateProvider(),
        );
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerFailedJobHandling();

        RateLimiter::for('api', fn () => Limit::perMinute(60)->by(request()->user()?->id ?: request()->ip()));
        RateLimiter::for('customer-register', fn () => Limit::perMinute(5)->by(request()->ip()));
        RateLimiter::for('customer-login', fn () => Limit::perMinute(5)->by(request()->ip()));
        RateLimiter::for('customer-forgot-password', fn () => Limit::perMinute(3)->by(request()->ip()));
        RateLimiter::for('customer-reset-password', fn () => Limit::perMinute(3)->by(request()->ip()));
        Gate::policy(ProductModel::class, ProductPolicy::class);
        Gate::policy(OrderModel::class, OrderPolicy::class);
        Gate::policy(CustomerSummaryModel::class, CustomerPolicy::class);
        Gate::policy(Customer::class, CustomerIdentityPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(InventoryLocation::class, InventoryLocationPolicy::class);
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

    private function registerRateLimiters(): void
    {
        RateLimiter::for('checkout', fn () => Limit::perMinute(30)->by(request()->user()?->id ?: request()->ip()));
        RateLimiter::for('payment', fn () => Limit::perMinute(20)->by(request()->user()?->id ?: request()->ip()));
        RateLimiter::for('payment-confirm', fn () => Limit::perMinute(10)->by(request()->user()?->id ?: request()->ip()));
        RateLimiter::for('webhook', fn () => Limit::perMinute(120)->by(request()->ip()));
        RateLimiter::for('login', fn () => Limit::perMinute(10)->by(request()->ip()));
    }

    private function registerFailedJobHandling(): void
    {
        Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event): void {
            Log::error('Queue job failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->getName(),
                'exception' => $event->exception->getMessage(),
                'uuid' => $event->job->uuid(),
            ]);
            if (class_exists(\App\Events\JobFailed::class)) {
                event(new \App\Events\JobFailed(
                    $event->connectionName,
                    $event->job->getQueue(),
                    $event->job->payload(),
                    $event->exception
                ));
            }
        });
    }
}
