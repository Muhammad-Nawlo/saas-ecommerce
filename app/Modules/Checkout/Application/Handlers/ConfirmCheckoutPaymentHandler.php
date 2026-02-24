<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Handlers;

use App\Modules\Checkout\Application\Commands\ConfirmCheckoutPaymentCommand;
use App\Modules\Checkout\Application\Services\CheckoutOrchestrator;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ConfirmCheckoutPaymentHandler
{
    public function __construct(
        private CheckoutOrchestrator $orchestrator,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ConfirmCheckoutPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $this->orchestrator->confirmPayment($command);
        });
    }
}
