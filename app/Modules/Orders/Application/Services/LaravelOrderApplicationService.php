<?php

declare(strict_types=1);

namespace App\Modules\Orders\Application\Services;

use App\Modules\Orders\Application\Commands\MarkOrderPaidCommand;
use App\Modules\Orders\Application\Handlers\MarkOrderPaidHandler;

final readonly class LaravelOrderApplicationService implements OrderApplicationService
{
    public function __construct(
        private MarkOrderPaidHandler $markOrderPaidHandler
    ) {
    }

    public function markOrderAsPaid(string $orderId): void
    {
        ($this->markOrderPaidHandler)(new MarkOrderPaidCommand(orderId: $orderId));
    }
}
