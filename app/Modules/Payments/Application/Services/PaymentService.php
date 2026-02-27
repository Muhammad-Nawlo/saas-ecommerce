<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Services;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;

/**
 * PaymentService
 *
 * Application service for payment operations: create payment intent (Stripe), confirm payment, refund, and dispatch PaymentSucceeded via EventBus.
 * Used by CheckoutOrchestrator and PaymentController (API).
 *
 * Assumes tenant context (payment records are tenant-scoped).
 *
 * Side effects:
 * - createPaymentIntent: External API call (Stripe); no DB write in this service (payment entity created by handler).
 * - confirmPayment: Stripe API call; gateway may update payment status; callers persist and dispatch events.
 * - refundPayment: Stripe API refund.
 * - dispatchPaymentSucceededIfAny: Publishes PaymentSucceeded to EventBus (listeners: Invoice, Ledger, Financial sync, OrderPaid, SendOrderConfirmation).
 *
 * Idempotency for confirm/refund must be enforced by callers or gateway.
 */
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
     * Create a payment intent at the gateway (e.g. Stripe) for the given payment entity.
     *
     * @param Payment $payment The payment entity (amount, currency, provider).
     * @param array<string, string> $metadata Optional metadata sent to gateway.
     * @return array{client_secret: string, provider_payment_id: string} For client-side confirmation.
     * Side effects: External API call (Stripe). No DB write in this method. Requires tenant context (payment is tenant-scoped).
     */
    public function createPaymentIntent(Payment $payment, array $metadata): array
    {
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        return $gateway->createPaymentIntent($payment->amount(), $metadata);
    }

    /**
     * Confirm a payment at the gateway (e.g. Stripe confirm PaymentIntent). Caller must persist status and dispatch PaymentSucceeded if applicable.
     *
     * @param Payment $payment The payment entity; must have providerPaymentId set.
     * @return void
     * @throws \InvalidArgumentException When payment has no provider payment id.
     * Side effects: Stripe API call. Does not write DB or dispatch events; caller responsibility. Requires tenant context.
     */
    public function confirmPayment(Payment $payment): void
    {
        $providerPaymentId = $payment->providerPaymentId();
        if ($providerPaymentId === null || $providerPaymentId === '') {
            throw new \InvalidArgumentException('Payment has no provider payment id');
        }
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        $gateway->confirmPayment($providerPaymentId);
    }

    /**
     * Refund a payment at the gateway (Stripe refund). Does not create FinancialTransaction or Ledger reversal; listeners handle OrderRefunded.
     *
     * @param Payment $payment The payment entity; must have providerPaymentId set.
     * @return void
     * @throws \InvalidArgumentException When payment has no provider payment id.
     * Side effects: Stripe API refund. Requires tenant context.
     */
    public function refundPayment(Payment $payment): void
    {
        $providerPaymentId = $payment->providerPaymentId();
        if ($providerPaymentId === null || $providerPaymentId === '') {
            throw new \InvalidArgumentException('Payment has no provider payment id');
        }
        $gateway = $this->gatewayResolver->resolve($payment->provider());
        $gateway->refund($providerPaymentId);
    }

    /**
     * Pull domain events from payment aggregate and publish PaymentSucceeded to EventBus (if any). Triggers Invoice, Ledger, Financial sync, OrderPaid, SendOrderConfirmation listeners.
     *
     * @param Payment $payment The payment entity (after confirmation) that may have recorded PaymentSucceeded.
     * @return void
     * Side effects: EventBus publish when EventBus is set and event is PaymentSucceeded. Requires tenant context.
     */
    public function dispatchPaymentSucceededIfAny(Payment $payment): void
    {
        foreach ($payment->pullDomainEvents() as $event) {
            if ($event instanceof PaymentSucceeded && $this->eventBus !== null) {
                $this->eventBus->publish($event);
            }
        }
    }
}
