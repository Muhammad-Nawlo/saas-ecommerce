<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Persistence;

use App\Landlord\Billing\Domain\Entities\Plan;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Domain\ValueObjects\BillingInterval;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;

final readonly class EloquentPlanRepository implements PlanRepository
{
    public function __construct(
        private PlanModel $model
    ) {
    }

    public function save(Plan $plan): void
    {
        PlanModel::on($this->model->getConnectionName())->updateOrCreate(
            ['id' => $plan->id()->value()],
            [
                'name' => $plan->name(),
                'stripe_price_id' => $plan->stripePriceId(),
                'price_amount' => $plan->priceAmount(),
                'currency' => $plan->currency(),
                'billing_interval' => $plan->billingInterval()->value(),
                'is_active' => $plan->isActive(),
                'created_at' => $plan->createdAt(),
                'updated_at' => $plan->updatedAt(),
            ]
        );
    }

    public function findById(PlanId $id): ?Plan
    {
        $model = PlanModel::on($this->model->getConnectionName())->find($id->value());
        return $model === null ? null : $this->toDomain($model);
    }

    public function findByStripePriceId(string $stripePriceId): ?Plan
    {
        $model = PlanModel::on($this->model->getConnectionName())
            ->where('stripe_price_id', $stripePriceId)
            ->first();
        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * @return list<Plan>
     */
    public function findActivePlans(): array
    {
        $models = PlanModel::on($this->model->getConnectionName())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $result = [];
        foreach ($models as $m) {
            $result[] = $this->toDomain($m);
        }
        return $result;
    }

    private function toDomain(PlanModel $m): Plan
    {
        return Plan::reconstitute(
            PlanId::fromString($m->id),
            $m->name,
            $m->stripe_price_id,
            $m->price_amount,
            $m->currency,
            BillingInterval::fromString($m->billing_interval),
            $m->is_active,
            $m->created_at->toDateTimeImmutable(),
            $m->updated_at->toDateTimeImmutable()
        );
    }
}
