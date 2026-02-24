<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Services;

use App\Modules\Checkout\Application\Contracts\PaymentService;
use App\Modules\Checkout\Application\Exceptions\PaymentInitializationException;
use App\Modules\Payments\Application\Commands\ConfirmPaymentCommand;
use App\Modules\Payments\Application\Commands\CreatePaymentCommand;
use App\Modules\Payments\Application\Handlers\ConfirmPaymentHandler;
use App\Modules\Payments\Application\Handlers\CreatePaymentHandler;
use App\Modules\Shared\Domain\ValueObjects\Money;
use Throwable;

final readonly class CheckoutPaymentService implements PaymentService
{
    public function __construct(
        private CreatePaymentHandler $createPaymentHandler,
        private ConfirmPaymentHandler $confirmPaymentHandler
    ) {
    }

    public function createPayment(string $orderId, Money $amount, string $provider): array
    {
        $tenantId = (string) tenant('id');
        $command = new CreatePaymentCommand(
            tenantId: $tenantId,
            orderId: $orderId,
            amountMinorUnits: $amount->amountInMinorUnits(),
            currency: $amount->currency(),
            provider: $provider
        );
        try {
            [$payment, $clientSecret] = ($this->createPaymentHandler)($command);
            return [
                'payment_id' => $payment->id()->value(),
                'client_secret' => $clientSecret,
            ];
        } catch (Throwable $e) {
            throw PaymentInitializationException::because($e->getMessage());
        }
    }

    public function confirmPayment(string $paymentId, string $providerPaymentId = ''): void
    {
        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            providerPaymentId: $providerPaymentId
        );
        ($this->confirmPaymentHandler)($command);
    }
}
