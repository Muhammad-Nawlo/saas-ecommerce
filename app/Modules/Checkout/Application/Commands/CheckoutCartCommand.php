<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Commands;

use App\Modules\Shared\Domain\Contracts\Command;

final readonly class CheckoutCartCommand implements Command
{
    /**
     * @param list<string>|null $couponCodes Optional coupon codes for promotion evaluation
     */
    public function __construct(
        public string $cartId,
        public string $paymentProvider,
        public string $customerEmail,
        public ?string $customerId = null,
        public ?array $couponCodes = null,
    ) {
    }
}
