<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Handlers;

use App\Landlord\Billing\Application\Commands\CancelSubscriptionCommand;
use App\Landlord\Billing\Application\Services\BillingService;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CancelSubscriptionHandler
{
    public function __construct(
        private BillingService $billingService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CancelSubscriptionCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $this->billingService->cancelSubscription($command);
        });
    }
}
