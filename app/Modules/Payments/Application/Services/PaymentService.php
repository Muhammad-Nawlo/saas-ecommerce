<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Services;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Entities\Payment;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;

final readonly class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentGatewayResolver $gatewayResolver,
        private ?EventBus $eventBus = null
    ) {
    }

    /**
     * @param array<string, string> $metadata
     * @return array{client_secret: string, provider_payment_id: string}
     */
    public function createPaymentIntent(Payment $payment, array $metadata): array
    {
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        return $gateway->createPaymentIntent($payment->amount(), $metadata);
    }

    public function confirmPayment(Payment $payment): void
    {
        $providerPaymentId = $payment->providerPaymentId();
        if ($providerPaymentId === null || $providerPaymentId === '') {
            throw new \InvalidArgumentException('Payment has no provider payment id');
        }
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        $gateway->confirmPayment($providerPaymentId);
    }

    public function refundPayment(Payment $payment): void
    {
        $providerPaymentId = $payment->providerPaymentId();
        if ($providerPaymentId === null || $providerPaymentId === '') {
            throw new \InvalidArgumentException('Payment has no provider payment id');
        }
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        $gateway->refund($providerPaymentId);
    }

    public function dispatchPaymentSucceededIfAny(Payment $payment): void
    {
        foreach ($payment->pullDomainEvents() as $event) {
            if ($event instanceof PaymentSucceeded && $this->eventBus !== null) {
                $this->eventBus->publish($event);
            }
        }
    }
}
