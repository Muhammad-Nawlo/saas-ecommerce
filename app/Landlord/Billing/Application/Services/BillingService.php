<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Application\Services;

use App\Landlord\Billing\Application\Commands\CancelSubscriptionCommand;
use App\Landlord\Billing\Application\Commands\SubscribeTenantCommand;
use App\Landlord\Billing\Application\Commands\SyncStripeSubscriptionCommand;
use App\Landlord\Billing\Domain\Contracts\StripeBillingGateway;
use App\Landlord\Billing\Domain\Entities\Plan;
use App\Landlord\Billing\Domain\Entities\Subscription;
use App\Landlord\Billing\Domain\Repositories\PlanRepository;
use App\Landlord\Billing\Domain\Repositories\SubscriptionRepository;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;
use App\Landlord\Billing\Domain\ValueObjects\StripeSubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionStatus;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BillingService
{
    public function __construct(
        private PlanRepository $planRepository,
        private SubscriptionRepository $subscriptionRepository,
        private StripeBillingGateway $stripeGateway,
        private Dispatcher $events
    ) {
    }

    public function subscribeTenant(SubscribeTenantCommand $command): Subscription
    {
        $planId = PlanId::fromString($command->planId);
        $plan = $this->planRepository->findById($planId);
        if ($plan === null) {
            throw new DomainException('Plan not found');
        }
        if (!$plan->isActive()) {
            throw new DomainException('Plan is not active');
        }
        $existing = $this->subscriptionRepository->findByTenantId($command->tenantId);
        if ($existing !== null && $existing->status()->isActive()) {
            throw new DomainException('Tenant already has an active subscription');
        }

        $customerId = $this->stripeGateway->createCustomer($command->customerEmail, [
            'tenant_id' => $command->tenantId,
        ]);
        $stripeData = $this->stripeGateway->createSubscription($customerId, $plan->stripePriceId());

        $subscriptionId = SubscriptionId::generate();
        $stripeSubId = StripeSubscriptionId::fromString($stripeData['id']);
        $periodStart = (new \DateTimeImmutable())->setTimestamp($stripeData['current_period_start']);
        $periodEnd = (new \DateTimeImmutable())->setTimestamp($stripeData['current_period_end']);
        $subscription = Subscription::create(
            $subscriptionId,
            $command->tenantId,
            $planId,
            $stripeSubId,
            $periodStart,
            $periodEnd,
            $stripeData['cancel_at_period_end']
        );
        $status = SubscriptionStatus::fromString($stripeData['status']);
        if ($status->isActive()) {
            $subscription->markActive();
        } elseif ($status->isTrialing()) {
            $subscription->markTrialing();
        } elseif ($status->isPastDue()) {
            $subscription->markPastDue();
        }

        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }
        return $subscription;
    }

    public function cancelSubscription(CancelSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findByTenantId($command->tenantId);
        if ($subscription === null) {
            throw new DomainException('Subscription not found');
        }
        $this->stripeGateway->cancelSubscription($subscription->stripeSubscriptionId()->value());
        $subscription->markCancelled();
        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }
    }

    public function syncSubscriptionFromStripe(SyncStripeSubscriptionCommand $command): Subscription
    {
        $subscription = $this->subscriptionRepository->findByStripeSubscriptionId($command->stripeSubscriptionId);
        if ($subscription === null) {
            throw new DomainException('Subscription not found');
        }
        $stripeData = $this->stripeGateway->retrieveSubscription($command->stripeSubscriptionId);
        $subscription->syncFromStripe(
            $stripeData['status'],
            $stripeData['current_period_start'],
            $stripeData['current_period_end'],
            $stripeData['cancel_at_period_end']
        );
        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }
        return $subscription;
    }
}
