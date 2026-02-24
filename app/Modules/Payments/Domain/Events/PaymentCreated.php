<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Events;

use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Shared\Domain\Contracts\DomainEvent;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final readonly class PaymentCreated implements DomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public string $orderId,
        public int $amountMinorUnits,
        public string $currency,
        public string $provider,
        public \DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
