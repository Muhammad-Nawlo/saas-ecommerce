<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Repositories;

use App\Landlord\Billing\Domain\Entities\Plan;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;

interface PlanRepository
{
    public function save(Plan $plan): void;

    public function findById(PlanId $id): ?Plan;

    /**
     * @return list<Plan>
     */
    public function findActivePlans(): array;
}
