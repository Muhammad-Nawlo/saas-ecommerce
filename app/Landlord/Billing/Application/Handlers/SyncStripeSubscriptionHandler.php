<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Handlers;

use App\Landlord\Billing\Application\Commands\SyncStripeSubscriptionCommand;
use App\Landlord\Billing\Application\Services\BillingService;
use App\Landlord\Billing\Domain\Entities\Subscription;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class SyncStripeSubscriptionHandler
{
    public function __construct(
        private BillingService $billingService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(SyncStripeSubscriptionCommand $command): Subscription
    {
        return $this->transactionManager->run(fn () => $this->billingService->syncSubscriptionFromStripe($command));
    }
}
