<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Handlers;

use App\Modules\Checkout\Application\Commands\CheckoutCartCommand;
use App\Modules\Checkout\Application\DTOs\CheckoutResponseDTO;
use App\Modules\Checkout\Application\Services\CheckoutOrchestrator;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CheckoutCartHandler
{
    public function __construct(
        private CheckoutOrchestrator $orchestrator,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CheckoutCartCommand $command): CheckoutResponseDTO
    {
        return $this->transactionManager->run(function () use ($command): CheckoutResponseDTO {
            return $this->orchestrator->checkout($command);
        });
    }
}
