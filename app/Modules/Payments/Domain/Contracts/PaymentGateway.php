<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Shared\Domain\ValueObjects\Money;

interface PaymentGateway
{
    /**
     * @param array<string, string> $metadata
     * @return array{client_secret: string, provider_payment_id: string}
     */
    public function createPaymentIntent(Money $amount, array $metadata): array;

    public function confirmPayment(string $providerPaymentId): void;

    public function refund(string $providerPaymentId): void;
}
