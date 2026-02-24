<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Gateways;

use App\Modules\Payments\Application\Services\PaymentGatewayResolver;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;

final class LaravelPaymentGatewayResolver implements PaymentGatewayResolver
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private array $gateways = [];

    public function __construct()
    {
        $this->gateways[PaymentProvider::STRIPE] = StripePaymentGateway::class;
    }

    public function resolve(PaymentProvider $provider): PaymentGateway
    {
        $class = $this->gateways[$provider->value()] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException(sprintf('No gateway registered for provider: %s', $provider->value()));
        }
        return app($class);
    }

    /**
     * @param class-string<PaymentGateway> $gatewayClass
     */
    public function register(string $provider, string $gatewayClass): void
    {
        $this->gateways[$provider] = $gatewayClass;
    }
}
