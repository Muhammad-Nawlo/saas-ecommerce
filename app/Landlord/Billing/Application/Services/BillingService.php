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
use App\Landlord\Models\Subscription as SubscriptionModel;
use App\Landlord\Services\FeatureResolver;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class BillingService
{
    public function __construct(
        private PlanRepository $planRepository,
        private SubscriptionRepository $subscriptionRepository,
        private StripeBillingGateway $stripeGateway,
        private Dispatcher $events,
        private FeatureResolver $featureResolver,
        private AuditLogger $auditLogger,
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
        $beforeStatus = $subscription->status()->value();
        $this->stripeGateway->cancelSubscription($subscription->stripeSubscriptionId()->value());
        $subscription->markCancelled();
        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }
        $tenantId = $subscription->tenantId()->value();
        $model = SubscriptionModel::on(config('tenancy.database.central_connection'))->find($subscription->id()->value());
        $this->auditLogger->logStructuredLandlordAction(
            'subscription_cancelled',
            "Subscription cancelled for tenant {$tenantId}",
            $model,
            ['status' => $beforeStatus],
            ['status' => 'cancelled'],
            ['stripe_subscription_id' => $subscription->stripeSubscriptionId()->value()],
            $tenantId,
        );
        $this->featureResolver->invalidateCacheForTenant($tenantId);
    }

    public function syncSubscriptionFromStripe(SyncStripeSubscriptionCommand $command): Subscription
    {
        $subscription = $this->subscriptionRepository->findByStripeSubscriptionId($command->stripeSubscriptionId);
        if ($subscription === null) {
            throw new DomainException('Subscription not found');
        }
        $beforePlanId = $subscription->planId()->value();
        $beforeStatus = $subscription->status()->value();
        $beforePeriodEnd = $subscription->currentPeriodEnd()->getTimestamp();

        $stripeData = $this->stripeGateway->retrieveSubscription($command->stripeSubscriptionId);
        $subscription->syncFromStripe(
            $stripeData['status'],
            $stripeData['current_period_start'],
            $stripeData['current_period_end'],
            $stripeData['cancel_at_period_end']
        );
        $planChanged = false;
        $priceId = $stripeData['price_id'] ?? null;
        if ($priceId !== null && $priceId !== '') {
            $plan = $this->planRepository->findByStripePriceId($priceId);
            if ($plan !== null && $subscription->planId()->value() !== $plan->id()->value()) {
                $subscription->syncPlanFromStripe($plan->id());
                $planChanged = true;
            }
        }

        $this->subscriptionRepository->save($subscription);
        foreach ($subscription->pullDomainEvents() as $event) {
            $this->events->dispatch($event);
        }

        $tenantId = $subscription->tenantId()->value();
        $subId = $subscription->id()->value();
        $model = SubscriptionModel::on(config('tenancy.database.central_connection'))->find($subId);
        if ($planChanged) {
            \App\Support\Instrumentation::subscriptionChanged($tenantId, 'plan_changed', $subId);
            $this->auditLogger->logStructuredLandlordAction(
                'subscription_plan_changed',
                "Subscription plan changed for tenant {$tenantId}",
                $model,
                ['plan_id' => $beforePlanId],
                ['plan_id' => $subscription->planId()->value()],
                ['stripe_subscription_id' => $command->stripeSubscriptionId],
                $tenantId,
            );
        }
        if ($beforeStatus !== $subscription->status()->value()) {
            $newStatus = $subscription->status()->value();
            $subEvent = $newStatus === 'canceled' || $newStatus === 'cancelled' ? 'cancelled' : ($newStatus === 'past_due' ? 'payment_failure' : 'status_changed');
            \App\Support\Instrumentation::subscriptionChanged($tenantId, $subEvent, $subId);
            $eventType = $newStatus === 'canceled' || $newStatus === 'cancelled' ? 'subscription_cancelled'
                : ($newStatus === 'past_due' ? 'subscription_payment_failure' : 'subscription_status_changed');
            $this->auditLogger->logStructuredLandlordAction(
                $eventType,
                "Subscription {$eventType} for tenant {$tenantId}",
                $model,
                ['status' => $beforeStatus],
                ['status' => $newStatus],
                ['stripe_subscription_id' => $command->stripeSubscriptionId],
                $tenantId,
            );
        }
        if ($subscription->currentPeriodEnd()->getTimestamp() > $beforePeriodEnd) {
            \App\Support\Instrumentation::subscriptionChanged($tenantId, 'renewed', $subId);
            $this->auditLogger->logStructuredLandlordAction(
                'subscription_renewed',
                "Subscription renewed for tenant {$tenantId}",
                $model,
                ['current_period_end' => $beforePeriodEnd],
                ['current_period_end' => $subscription->currentPeriodEnd()->getTimestamp()],
                ['stripe_subscription_id' => $command->stripeSubscriptionId],
                $tenantId,
            );
        }

        $this->featureResolver->invalidateCacheForTenant($tenantId);
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
