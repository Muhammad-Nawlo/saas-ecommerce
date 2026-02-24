<?php

declare(strict_types=1);

namespace App\Landlord\Billing\Domain\Entities;

use App\Landlord\Billing\Domain\Events\PlanActivated;
use App\Landlord\Billing\Domain\Events\PlanCreated;
use App\Landlord\Billing\Domain\Events\PlanDeactivated;
use App\Landlord\Billing\Domain\ValueObjects\BillingInterval;
use App\Landlord\Billing\Domain\ValueObjects\PlanId;

final class Plan
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private PlanId $id,
        private string $name,
        private string $stripePriceId,
        private int $priceAmount,
        private string $currency,
        private BillingInterval $billingInterval,
        private bool $isActive,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        PlanId $id,
        string $name,
        string $stripePriceId,
        int $priceAmount,
        string $currency,
        BillingInterval $billingInterval
    ): self {
        $now = new \DateTimeImmutable();
        $plan = new self(
            $id,
            $name,
            trim($stripePriceId),
            $priceAmount,
            strtoupper(trim($currency)),
            $billingInterval,
            true,
            $now,
            $now
        );
        $plan->domainEvents[] = new PlanCreated($id, $name, $now);
        return $plan;
    }

    public static function reconstitute(
        PlanId $id,
        string $name,
        string $stripePriceId,
        int $priceAmount,
        string $currency,
        BillingInterval $billingInterval,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $name,
            $stripePriceId,
            $priceAmount,
            $currency,
            $billingInterval,
            $isActive,
            $createdAt,
            $updatedAt
        );
    }

    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
        $this->domainEvents[] = new PlanActivated($this->id, $this->updatedAt);
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
        $this->domainEvents[] = new PlanDeactivated($this->id, $this->updatedAt);
    }

    public function changePrice(int $priceAmount, string $currency): void
    {
        $this->priceAmount = $priceAmount;
        $this->currency = strtoupper(trim($currency));
        $this->updatedAt = new \DateTimeImmutable();
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

    public function id(): PlanId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function stripePriceId(): string
    {
        return $this->stripePriceId;
    }

    public function priceAmount(): int
    {
        return $this->priceAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function billingInterval(): BillingInterval
    {
        return $this->billingInterval;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
