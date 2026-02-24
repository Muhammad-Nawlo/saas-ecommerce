<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Inventory\Domain\Events\LowStockReached;
use App\Modules\Inventory\Domain\Events\StockCreated;
use App\Modules\Inventory\Domain\Events\StockDecreased;
use App\Modules\Inventory\Domain\Events\StockIncreased;
use App\Modules\Inventory\Domain\Events\StockReleased;
use App\Modules\Inventory\Domain\Events\StockReserved;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\ValueObjects\ProductId;
use App\Modules\Inventory\Domain\ValueObjects\Quantity;
use App\Modules\Inventory\Domain\ValueObjects\StockItemId;
use App\Modules\Inventory\Domain\ValueObjects\Threshold;
use App\Modules\Shared\Domain\Contracts\AggregateRoot;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Domain\ValueObjects\Uuid;

final class StockItem implements AggregateRoot
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private StockItemId $id,
        private TenantId $tenantId,
        private ProductId $productId,
        private int $quantity,
        private int $reservedQuantity,
        private int $lowStockThreshold,
        private \DateTimeImmutable $createdAt
    ) {
        if ($quantity < 0 || $reservedQuantity < 0 || $reservedQuantity > $quantity) {
            throw BusinessRuleViolation::because(
                'Invalid stock state: quantity and reserved_quantity must be >= 0, reserved_quantity <= quantity'
            );
        }
    }

    public static function create(
        StockItemId $id,
        TenantId $tenantId,
        ProductId $productId,
        Quantity $initialQuantity,
        Threshold $lowStockThreshold
    ): self {
        $q = $initialQuantity->value();
        $th = $lowStockThreshold->value();
        $item = new self($id, $tenantId, $productId, $q, 0, $th, new \DateTimeImmutable());
        $item->recordEvent(new StockCreated(
            $id,
            $tenantId,
            $productId->value(),
            $q,
            new \DateTimeImmutable()
        ));
        if ($th > 0 && $q <= $th) {
            $item->recordEvent(new LowStockReached($id, $q, $th, new \DateTimeImmutable()));
        }
        return $item;
    }

    public static function reconstitute(
        StockItemId $id,
        TenantId $tenantId,
        ProductId $productId,
        int $quantity,
        int $reservedQuantity,
        int $lowStockThreshold,
        \DateTimeImmutable $createdAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $productId,
            $quantity,
            $reservedQuantity,
            $lowStockThreshold,
            $createdAt
        );
    }

    public function increase(int $amount): void
    {
        if ($amount <= 0) {
            throw BusinessRuleViolation::because('Increase amount must be positive');
        }
        $this->quantity += $amount;
        $this->recordEvent(new StockIncreased(
            $this->id,
            $amount,
            $this->quantity,
            new \DateTimeImmutable()
        ));
    }

    public function decrease(int $amount): void
    {
        if ($amount <= 0) {
            throw BusinessRuleViolation::because('Decrease amount must be positive');
        }
        $available = $this->quantity - $this->reservedQuantity;
        if ($amount > $available) {
            throw InsufficientStockException::forQuantity($amount, $available);
        }
        $this->quantity -= $amount;
        $this->recordEvent(new StockDecreased(
            $this->id,
            $amount,
            $this->quantity,
            new \DateTimeImmutable()
        ));
    }

    public function reserve(int $amount): void
    {
        if ($amount <= 0) {
            throw BusinessRuleViolation::because('Reserve amount must be positive');
        }
        $available = $this->quantity - $this->reservedQuantity;
        if ($amount > $available) {
            throw InsufficientStockException::forQuantity($amount, $available);
        }
        $this->reservedQuantity += $amount;
        $this->recordEvent(new StockReserved(
            $this->id,
            $amount,
            $this->reservedQuantity,
            new \DateTimeImmutable()
        ));
    }

    public function release(int $amount): void
    {
        if ($amount <= 0) {
            throw BusinessRuleViolation::because('Release amount must be positive');
        }
        if ($amount > $this->reservedQuantity) {
            throw BusinessRuleViolation::because(
                sprintf('Cannot release %d: reserved quantity is %d', $amount, $this->reservedQuantity)
            );
        }
        $this->reservedQuantity -= $amount;
        $this->recordEvent(new StockReleased(
            $this->id,
            $amount,
            $this->reservedQuantity,
            new \DateTimeImmutable()
        ));
    }

    public function setLowStockThreshold(int $threshold): void
    {
        if ($threshold < 0) {
            throw BusinessRuleViolation::because('Low stock threshold cannot be negative');
        }
        $this->lowStockThreshold = $threshold;
        if ($threshold > 0 && $this->quantity <= $threshold) {
            $this->recordEvent(new LowStockReached(
                $this->id,
                $this->quantity,
                $this->lowStockThreshold,
                new \DateTimeImmutable()
            ));
        }
    }

    public function availableQuantity(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }

    public function isInStock(): bool
    {
        return $this->availableQuantity() > 0;
    }

    public function isLowStock(): bool
    {
        return $this->lowStockThreshold > 0 && $this->quantity <= $this->lowStockThreshold;
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

    public function id(): StockItemId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function reservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function lowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param object $event
     */
    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
