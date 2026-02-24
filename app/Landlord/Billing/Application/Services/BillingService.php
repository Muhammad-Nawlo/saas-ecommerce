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
use App\Landlord\Services\FeatureResolver;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BillingService
{
    public function __construct(
        private PlanRepository $planRepository,
        private SubscriptionRepository $subscriptionRepository,
        private StripeBillingGateway $stripeGateway,
        private Dispatcher $events,
        private FeatureResolver $featureResolver
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
        $this->featureResolver->invalidateCacheForTenant($command->tenantId);
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
        $this->featureResolver->invalidateCacheForTenant($subscription->tenantId()->value());
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
        // Sync plan when Stripe subscription price changed (upgrade/downgrade).
        $priceId = $stripeData['price_id'] ?? null;
        if ($priceId !== null && $priceId !== '') {
            $plan = $this->planRepository->findByStripePriceId($priceId);
            if ($plan !== null && $subscription->planId()->value() !== $plan->id()->value()) {
                $subscription->syncPlanFromStripe($plan->id());
            }
        }
        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }
        $this->featureResolver->invalidateCacheForTenant($subscription->tenantId()->value());
        return $subscription;
    }

    /**
     * Create or sync local subscription after Stripe Checkout session completed.
     * Updates tenant plan_id and stripe_customer_id.
     */
    public function createSubscriptionFromStripeCheckout(
        string $stripeSubscriptionId,
        string $tenantId,
        string $stripeCustomerId
    ): Subscription {
        $existing = $this->subscriptionRepository->findByStripeSubscriptionId($stripeSubscriptionId);
        if ($existing !== null) {
            $this->syncSubscriptionFromStripe(new SyncStripeSubscriptionCommand($stripeSubscriptionId));
            $this->updateTenantStripeAndPlan($tenantId, $existing->planId()->value(), $stripeCustomerId);
            return $existing;
        }

        $stripeData = $this->stripeGateway->retrieveSubscription($stripeSubscriptionId);
        $priceId = $stripeData['price_id'] ?? null;
        if ($priceId === null || $priceId === '') {
            throw new DomainException('Stripe subscription has no price');
        }
        $plan = $this->planRepository->findByStripePriceId($priceId);
        if ($plan === null) {
            throw new DomainException('Plan not found for Stripe price: ' . $priceId);
        }
        if (!$plan->isActive()) {
            throw new DomainException('Plan is not active');
        }

        $subscriptionId = SubscriptionId::generate();
        $stripeSubId = StripeSubscriptionId::fromString($stripeData['id']);
        $periodStart = (new \DateTimeImmutable())->setTimestamp($stripeData['current_period_start']);
        $periodEnd = (new \DateTimeImmutable())->setTimestamp($stripeData['current_period_end']);
        $subscription = Subscription::create(
            $subscriptionId,
            $tenantId,
            $plan->id(),
            $stripeSubId,
            $periodStart,
            $periodEnd,
            $stripeData['cancel_at_period_end']
        );
        $status = SubscriptionStatus::fromString($this->normalizeStripeStatus($stripeData['status']));
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
        $this->updateTenantStripeAndPlan($tenantId, $plan->id()->value(), $stripeCustomerId);
        $this->featureResolver->invalidateCacheForTenant($tenantId);
        return $subscription;
    }

    private function updateTenantStripeAndPlan(string $tenantId, string $planId, string $stripeCustomerId): void
    {
        $tenant = \App\Landlord\Models\Tenant::find($tenantId);
        if ($tenant === null) {
            return;
        }
        $tenant->plan_id = $planId;
        $tenant->stripe_customer_id = $stripeCustomerId;
        $tenant->status = 'active';
        $tenant->save();
    }

    private function normalizeStripeStatus(string $status): string
    {
        return $status === 'canceled' ? 'cancelled' : $status;
    }
}
