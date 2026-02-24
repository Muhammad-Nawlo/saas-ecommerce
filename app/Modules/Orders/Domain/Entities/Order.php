<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Entities;

use App\Modules\Orders\Domain\Events\OrderCancelled;
use App\Modules\Orders\Domain\Events\OrderConfirmed;
use App\Modules\Orders\Domain\Events\OrderCreated;
use App\Modules\Orders\Domain\Events\OrderItemAdded;
use App\Modules\Orders\Domain\Events\OrderPaid;
use App\Modules\Orders\Domain\Events\OrderShipped;
use App\Modules\Orders\Domain\ValueObjects\CustomerEmail;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Orders\Domain\ValueObjects\OrderItemId;
use App\Modules\Orders\Domain\ValueObjects\OrderStatus;
use App\Modules\Orders\Domain\ValueObjects\ProductId;
use App\Modules\Orders\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\Contracts\AggregateRoot;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Domain\ValueObjects\Uuid;

final class Order implements AggregateRoot
{
    /** @var list<object> */
    private array $domainEvents = [];

    /** @var list<OrderItem> */
    private array $items = [];

    private function __construct(
        private OrderId $id,
        private TenantId $tenantId,
        private CustomerEmail $customerEmail,
        private OrderStatus $status,
        private Money $totalAmount,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        OrderId $id,
        TenantId $tenantId,
        CustomerEmail $customerEmail
    ): self {
        $order = new self(
            $id,
            $tenantId,
            $customerEmail,
            OrderStatus::pending(),
            Money::fromMinorUnits(0, 'USD'),
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $order->recordEvent(new OrderCreated(
            $id,
            $tenantId,
            $customerEmail->value(),
            new \DateTimeImmutable()
        ));
        return $order;
    }

    public static function reconstitute(
        OrderId $id,
        TenantId $tenantId,
        CustomerEmail $customerEmail,
        OrderStatus $status,
        Money $totalAmount,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $items
    ): self {
        $order = new self($id, $tenantId, $customerEmail, $status, $totalAmount, $createdAt, $updatedAt);
        $order->items = $items;
        return $order;
    }

    public function addItem(ProductId $productId, Quantity $quantity, Money $unitPrice): void
    {
        if (!$this->status->isPending()) {
            throw BusinessRuleViolation::because('Cannot modify items after order is confirmed');
        }
        $itemId = OrderItemId::generate();
        $item = OrderItem::create($itemId, $productId, $quantity, $unitPrice);
        $this->items[] = $item;
        $this->recalculateTotal();
        $this->touchUpdatedAt();
        $this->recordEvent(new OrderItemAdded(
            $this->id,
            $itemId,
            $productId->value(),
            $quantity->value(),
            $unitPrice->amountInMinorUnits(),
            $unitPrice->currency(),
            new \DateTimeImmutable()
        ));
    }

    public function removeItem(ProductId $productId): void
    {
        if (!$this->status->isPending()) {
            throw BusinessRuleViolation::because('Cannot modify items after order is confirmed');
        }
        $before = count($this->items);
        $this->items = array_values(array_filter(
            $this->items,
            fn (OrderItem $item) => !$item->productId()->equals($productId)
        ));
        if (count($this->items) === $before) {
            return;
        }
        $this->recalculateTotal();
        $this->touchUpdatedAt();
    }

    public function confirm(): void
    {
        $newStatus = OrderStatus::confirmed();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot confirm order: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new OrderConfirmed($this->id, new \DateTimeImmutable()));
    }

    public function markAsPaid(): void
    {
        $newStatus = OrderStatus::paid();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot mark as paid: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new OrderPaid($this->id, new \DateTimeImmutable()));
    }

    public function ship(): void
    {
        $newStatus = OrderStatus::shipped();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot ship order: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new OrderShipped($this->id, new \DateTimeImmutable()));
    }

    public function cancel(): void
    {
        $newStatus = OrderStatus::cancelled();
        if (!$this->status->canTransitionTo($newStatus)) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot cancel order: invalid transition from %s to %s', $this->status->value(), $newStatus->value())
            );
        }
        $this->status = $newStatus;
        $this->touchUpdatedAt();
        $this->recordEvent(new OrderCancelled($this->id, new \DateTimeImmutable()));
    }

    public function calculateTotal(): Money
    {
        return $this->totalAmount;
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

    public function id(): OrderId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerEmail(): CustomerEmail
    {
        return $this->customerEmail;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    private function recalculateTotal(): void
    {
        $currency = null;
        $totalMinor = 0;
        foreach ($this->items as $item) {
            $totalMinor += $item->totalPrice()->amountInMinorUnits();
            $currency = $item->totalPrice()->currency();
        }
        $this->totalAmount = Money::fromMinorUnits($totalMinor, $currency ?? 'USD');
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
