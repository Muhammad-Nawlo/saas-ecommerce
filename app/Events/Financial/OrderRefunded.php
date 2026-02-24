<?php

declare(strict_types=1);

namespace App\Events\Financial;

use App\Models\Financial\FinancialOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FinancialOrder $order,
        public int $amountCents,
        public ?string $providerReference = null,
    ) {}
}
