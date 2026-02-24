<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Infrastructure\Persistence;

use App\Landlord\Billing\Domain\Entities\Subscription;
use App\Landlord\Billing\Domain\Repositories\SubscriptionRepository;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;
use App\Landlord\Billing\Domain\ValueObjects\StripeSubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionStatus;

final readonly class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function __construct(
        private SubscriptionModel $model
    ) {
    }

    public function save(Subscription $subscription): void
    {
        $conn = $this->model->getConnectionName();
        SubscriptionModel::on($conn)->updateOrCreate(
            ['id' => $subscription->id()->value()],
            [
                'tenant_id' => $subscription->tenantId(),
                'plan_id' => $subscription->planId()->value(),
                'stripe_subscription_id' => $subscription->stripeSubscriptionId()->value(),
                'status' => $subscription->status()->value(),
                'current_period_start' => $subscription->currentPeriodStart(),
                'current_period_end' => $subscription->currentPeriodEnd(),
                'cancel_at_period_end' => $subscription->cancelAtPeriodEnd(),
                'created_at' => $subscription->createdAt(),
                'updated_at' => $subscription->updatedAt(),
            ]
        );
    }

    public function findByTenantId(string $tenantId): ?Subscription
    {
        $model = SubscriptionModel::on($this->model->getConnectionName())
            ->where('tenant_id', $tenantId)
            ->first();
        return $model === null ? null : $this->toDomain($model);
    }

    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        $model = SubscriptionModel::on($this->model->getConnectionName())
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();
        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(SubscriptionModel $m): Subscription
    {
        return Subscription::reconstitute(
            SubscriptionId::fromString($m->id),
            $m->tenant_id,
            PlanId::fromString($m->plan_id),
            StripeSubscriptionId::fromString($m->stripe_subscription_id),
            SubscriptionStatus::fromString($m->status),
            $m->current_period_start->toDateTimeImmutable(),
            $m->current_period_end->toDateTimeImmutable(),
            $m->cancel_at_period_end,
            $m->created_at->toDateTimeImmutable(),
            $m->updated_at->toDateTimeImmutable()
        );
    }
}
