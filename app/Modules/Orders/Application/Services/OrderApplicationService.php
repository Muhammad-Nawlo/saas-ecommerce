<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Services;

interface OrderApplicationService
{
    public function markOrderAsPaid(string $orderId): void;
}
