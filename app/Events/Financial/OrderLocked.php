<?php

declare(strict_types=1);

namespace App\Events\Financial;

use App\Models\Financial\FinancialOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderLocked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FinancialOrder $order
    ) {}
}
