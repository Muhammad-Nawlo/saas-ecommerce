<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Handlers;

use App\Landlord\Billing\Application\Commands\CreatePlanCommand;
use App\Landlord\Billing\Domain\Entities\Plan;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Domain\ValueObjects\BillingInterval;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CreatePlanHandler
{
    public function __construct(
        private PlanRepository $planRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CreatePlanCommand $command): Plan
    {
        return $this->transactionManager->run(function () use ($command): Plan {
            $id = PlanId::generate();
            $plan = Plan::create(
                $id,
                $command->name,
                $command->stripePriceId,
                $command->priceAmount,
                $command->currency,
                BillingInterval::fromString($command->billingInterval)
            );
            $this->planRepository->save($plan);
            return $plan;
        });
    }
}
