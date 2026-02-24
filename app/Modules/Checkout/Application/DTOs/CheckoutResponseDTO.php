<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\DTOs;

final readonly class CheckoutResponseDTO
{
    public function __construct(
        public string $orderId,
        public string $paymentId,
        public string $clientSecret,
        public int $amount,
        public string $currency
    ) {
    }
}
