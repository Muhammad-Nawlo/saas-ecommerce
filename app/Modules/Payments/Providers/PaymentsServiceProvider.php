<?php

declare(strict_types=1);

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Infrastructure\Gateways\LaravelPaymentGatewayResolver;
use App\Modules\Payments\Infrastructure\Gateways\StripePaymentGateway;
use App\Modules\Payments\Infrastructure\Persistence\EloquentPaymentRepository;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentRepository::class,
            EloquentPaymentRepository::class
        );
        $this->app->singleton(
            PaymentGatewayResolver::class,
            LaravelPaymentGatewayResolver::class
        );
    }

    public function boot(): void
    {
        //
    }
}
