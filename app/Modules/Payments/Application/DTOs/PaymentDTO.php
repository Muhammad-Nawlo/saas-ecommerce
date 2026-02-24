<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Payments\Domain\Entities\Payment;

final readonly class PaymentDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $orderId,
        public int $amountMinorUnits,
        public string $currency,
        public string $status,
        public string $provider,
        public ?string $providerPaymentId,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromPayment(Payment $payment): self
    {
        return new self(
            $payment->id()->value(),
            $payment->tenantId()->value(),
            $payment->orderId()->value(),
            $payment->amount()->amountInMinorUnits(),
            $payment->amount()->currency(),
            $payment->status()->value(),
            $payment->provider()->value(),
            $payment->providerPaymentId(),
            $payment->createdAt()->format(\DateTimeInterface::ATOM),
            $payment->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
