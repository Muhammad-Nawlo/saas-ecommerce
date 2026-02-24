<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Contracts;

use App\Modules\Shared\Domain\ValueObjects\Money;

interface PaymentService
{
    /**
     * @return array{payment_id: string, client_secret: string}
     */
    public function createPayment(string $orderId, Money $amount, string $provider): array;

    public function confirmPayment(string $paymentId, string $providerPaymentId = ''): void;
}
