<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Providers;

use App\Landlord\Billing\Domain\Contracts\StripeBillingGateway;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Domain\Repositories\SubscriptionRepository;
use App\Landlord\Billing\Infrastructure\Gateways\StripeBillingGatewayImplementation;
use App\Landlord\Billing\Infrastructure\Persistence\EloquentPlanRepository;
use App\Landlord\Billing\Infrastructure\Persistence\EloquentSubscriptionRepository;
use App\Landlord\Billing\Infrastructure\Persistence\PlanModel;
use App\Landlord\Billing\Infrastructure\Persistence\SubscriptionModel;
use App\Modules\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(PlanRepository::class, function (): EloquentPlanRepository {
            return new EloquentPlanRepository(new PlanModel());
        });
        $this->app->bind(SubscriptionRepository::class, function (): EloquentSubscriptionRepository {
            return new EloquentSubscriptionRepository(new SubscriptionModel());
        });
        $this->app->singleton(StripeBillingGateway::class, function (): StripeBillingGateway {
            return StripeBillingGatewayImplementation::fromConfig();
        });
    }

    public function boot(): void
    {
        //
    }
}
