<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Entities;

use App\Modules\Payments\Domain\Events\PaymentAuthorized;
use App\Modules\Payments\Domain\Events\PaymentCancelled;
use App\Modules\Payments\Domain\Events\PaymentCreated;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Events\PaymentRefunded;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;
use App\Modules\Payments\Domain\ValueObjects\PaymentStatus;
use App\Modules\Shared\Domain\Contracts\AggregateRoot;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Domain\ValueObjects\Uuid;

final class Payment implements AggregateRoot
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private PaymentId $id,
        private TenantId $tenantId,
        private OrderId $orderId,
        private Money $amount,
        private PaymentStatus $status,
        private PaymentProvider $provider,
        private ?string $providerPaymentId,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        PaymentId $id,
        TenantId $tenantId,
        OrderId $orderId,
        Money $amount,
        PaymentProvider $provider
    ): self {
        $payment = new self(
            $id,
            $tenantId,
            $orderId,
            $amount,
            PaymentStatus::pending(),
            $provider,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $payment->recordEvent(new PaymentCreated(
            $id,
            $tenantId,
            $orderId->value(),
            $amount->amountInMinorUnits(),
            $amount->currency(),
            $provider->value(),
            new \DateTimeImmutable()
        ));
        return $payment;
    }

    public static function reconstitute(
        PaymentId $id,
        TenantId $tenantId,
        OrderId $orderId,
        Money $amount,
        PaymentStatus $status,
        PaymentProvider $provider,
        ?string $providerPaymentId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $orderId,
            $amount,
            $status,
            $provider,
            $providerPaymentId,
            $createdAt,
            $updatedAt
        );
    }

    public function authorize(string $providerPaymentId): void
    {
        $newStatus = PaymentStatus::authorized();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot authorize: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->providerPaymentId = $providerPaymentId;
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new PaymentAuthorized($this->id, $providerPaymentId, new \DateTimeImmutable()));
    }

    public function markSucceeded(): void
    {
        $newStatus = PaymentStatus::succeeded();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot mark succeeded: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new PaymentSucceeded($this->id, $this->orderId->value(), new \DateTimeImmutable()));
    }

    public function markFailed(): void
    {
        $newStatus = PaymentStatus::failed();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot mark failed: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new PaymentFailed($this->id, new \DateTimeImmutable()));
    }

    public function refund(): void
    {
        $newStatus = PaymentStatus::refunded();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot refund: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new PaymentRefunded($this->id, new \DateTimeImmutable()));
    }

    public function cancel(): void
    {
        $newStatus = PaymentStatus::cancelled();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot cancel: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new PaymentCancelled($this->id, new \DateTimeImmutable()));
    }

    public function setProviderPaymentId(string $providerPaymentId): void
    {
        if (!$this->status->equals(PaymentStatus::pending())) {
            throw BusinessRuleViolation::because('Can only set provider payment id when payment is pending');
        }
        $this->providerPaymentId = $providerPaymentId;
        $this->touchUpdatedAt();
    }

    public function getId(): Uuid
    {
        return $this->id->toUuid();
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

    public function id(): PaymentId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function providerPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param object $event
     */
    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
