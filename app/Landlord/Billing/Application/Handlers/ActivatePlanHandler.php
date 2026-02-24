<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Handlers;

use App\Landlord\Billing\Application\Commands\ActivatePlanCommand;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class ActivatePlanHandler
{
    public function __construct(
        private PlanRepository $planRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(ActivatePlanCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $planId = PlanId::fromString($command->planId);
            $plan = $this->planRepository->findById($planId);
            if ($plan === null) {
                throw new DomainException('Plan not found');
            }
            $plan->activate();
            $this->planRepository->save($plan);
        });
    }
}
