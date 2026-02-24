<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Entities;

use App\Landlord\Billing\Domain\Events\SubscriptionActivated;
use App\Landlord\Billing\Domain\Events\SubscriptionCancelled;
use App\Landlord\Billing\Domain\Events\SubscriptionCreated;
use App\Landlord\Billing\Domain\Events\SubscriptionPastDue;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;
use App\Landlord\Billing\Domain\ValueObjects\StripeSubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionId;
use App\Landlord\Billing\Domain\ValueObjects\SubscriptionStatus;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;

final class Subscription
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private SubscriptionId $id,
        private string $tenantId,
        private PlanId $planId,
        private StripeSubscriptionId $stripeSubscriptionId,
        private SubscriptionStatus $status,
        private \DateTimeImmutable $currentPeriodStart,
        private \DateTimeImmutable $currentPeriodEnd,
        private bool $cancelAtPeriodEnd,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        SubscriptionId $id,
        string $tenantId,
        PlanId $planId,
        StripeSubscriptionId $stripeSubscriptionId,
        \DateTimeImmutable $currentPeriodStart,
        \DateTimeImmutable $currentPeriodEnd,
        bool $cancelAtPeriodEnd = false
    ): self {
        $now = new \DateTimeImmutable();
        $sub = new self(
            $id,
            $tenantId,
            $planId,
            $stripeSubscriptionId,
            SubscriptionStatus::incomplete(),
            $currentPeriodStart,
            $currentPeriodEnd,
            $cancelAtPeriodEnd,
            $now,
            $now
        );
        $sub->domainEvents[] = new SubscriptionCreated(
            $id,
            $tenantId,
            $planId->value(),
            $now
        );
        return $sub;
    }

    public static function reconstitute(
        SubscriptionId $id,
        string $tenantId,
        PlanId $planId,
        StripeSubscriptionId $stripeSubscriptionId,
        SubscriptionStatus $status,
        \DateTimeImmutable $currentPeriodStart,
        \DateTimeImmutable $currentPeriodEnd,
        bool $cancelAtPeriodEnd,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $planId,
            $stripeSubscriptionId,
            $status,
            $currentPeriodStart,
            $currentPeriodEnd,
            $cancelAtPeriodEnd,
            $createdAt,
            $updatedAt
        );
    }

    public function markActive(): void
    {
        $this->transitionTo(SubscriptionStatus::active(), [
            SubscriptionStatus::INCOMPLETE,
            SubscriptionStatus::TRIALING,
        ], SubscriptionActivated::class);
    }

    public function markPastDue(): void
    {
        $this->transitionTo(SubscriptionStatus::pastDue(), [SubscriptionStatus::ACTIVE], SubscriptionPastDue::class);
    }

    public function markCancelled(): void
    {
        $this->transitionTo(SubscriptionStatus::cancelled(), [SubscriptionStatus::ACTIVE], SubscriptionCancelled::class);
    }

    public function markUnpaid(): void
    {
        $this->status = SubscriptionStatus::unpaid();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markTrialing(): void
    {
        $this->status = SubscriptionStatus::trialing();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function syncFromStripe(
        string $status,
        int $currentPeriodStartTs,
        int $currentPeriodEndTs,
        bool $cancelAtPeriodEnd
    ): void {
        $normalized = $status === 'canceled' ? 'cancelled' : $status;
        $newStatus = SubscriptionStatus::fromString($normalized);
        $this->status = $newStatus;
        $this->currentPeriodStart = (new \DateTimeImmutable())->setTimestamp($currentPeriodStartTs);
        $this->currentPeriodEnd = (new \DateTimeImmutable())->setTimestamp($currentPeriodEndTs);
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param list<string> $allowedFrom
     * @param class-string $eventClass
     */
    private function transitionTo(SubscriptionStatus $newStatus, array $allowedFrom, string $eventClass): void
    {
        $current = $this->status->value();
        if (!in_array($current, $allowedFrom, true)) {
            throw BusinessRuleViolation::because(
                "Cannot transition subscription from {$current} to " . $newStatus->value()
            );
        }
        $this->status = $newStatus;
        $this->updatedAt = new \DateTimeImmutable();
        $this->domainEvents[] = new $eventClass($this->id, $this->updatedAt);
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function id(): SubscriptionId
    {
        return $this->id;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function stripeSubscriptionId(): StripeSubscriptionId
    {
        return $this->stripeSubscriptionId;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function currentPeriodStart(): \DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function currentPeriodEnd(): \DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function cancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
