<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Entities;

use App\Modules\Cart\Domain\Events\CartAbandoned;
use App\Modules\Cart\Domain\Events\CartCleared;
use App\Modules\Cart\Domain\Events\CartConverted;
use App\Modules\Cart\Domain\Events\CartCreated;
use App\Modules\Cart\Domain\Events\CartItemAdded;
use App\Modules\Cart\Domain\Events\CartItemRemoved;
use App\Modules\Cart\Domain\Events\CartItemUpdated;
use App\Modules\Cart\Domain\Exceptions\CartAlreadyConvertedException;
use App\Modules\Cart\Domain\Exceptions\InvalidCartStateException;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Domain\ValueObjects\CartItemId;
use App\Modules\Cart\Domain\ValueObjects\CartStatus;
use App\Modules\Cart\Domain\ValueObjects\CustomerEmail;
use App\Modules\Cart\Domain\ValueObjects\ProductId;
use App\Modules\Cart\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;

final class Cart
{
    /** @var list<object> */
    private array $domainEvents = [];

    /** @var list<CartItem> */
    private array $items = [];

    private function __construct(
        private CartId $id,
        private TenantId $tenantId,
        private ?CustomerEmail $customerEmail,
        private ?string $sessionId,
        private CartStatus $status,
        private Money $totalAmount,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        CartId $id,
        TenantId $tenantId,
        ?CustomerEmail $customerEmail,
        ?string $sessionId
    ): self {
        if ($customerEmail === null && ($sessionId === null || trim($sessionId) === '')) {
            throw InvalidCartStateException::because('Either customer_email or session_id must be provided');
        }
        $cart = new self(
            $id,
            $tenantId,
            $customerEmail,
            $sessionId !== null ? trim($sessionId) : null,
            CartStatus::active(),
            Money::fromMinorUnits(0, 'USD'),
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $cart->recordEvent(new CartCreated(
            $id,
            $tenantId,
            $customerEmail?->value(),
            $cart->sessionId,
            new \DateTimeImmutable()
        ));
        return $cart;
    }

    public static function reconstitute(
        CartId $id,
        TenantId $tenantId,
        ?CustomerEmail $customerEmail,
        ?string $sessionId,
        CartStatus $status,
        Money $totalAmount,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        array $items
    ): self {
        $cart = new self(
            $id,
            $tenantId,
            $customerEmail,
            $sessionId,
            $status,
            $totalAmount,
            $createdAt,
            $updatedAt
        );
        $cart->items = $items;
        return $cart;
    }

    public function addItem(ProductId $productId, Quantity $quantity, Money $unitPrice): void
    {
        $this->assertNotConverted();
        foreach ($this->items as $i => $item) {
            if ($item->productId()->equals($productId)) {
                $newQty = $item->quantity() + $quantity->value();
                $this->items[$i] = $item->withQuantity($newQty);
                $this->recalculateTotal();
                $this->touchUpdatedAt();
                $this->recordEvent(new CartItemUpdated(
                    $this->id,
                    $productId->value(),
                    $newQty,
                    new \DateTimeImmutable()
                ));
                return;
            }
        }
        $itemId = CartItemId::generate();
        $item = CartItem::create($itemId, $productId, $quantity, $unitPrice);
        $this->items[] = $item;
        $this->recalculateTotal();
        $this->touchUpdatedAt();
        $this->recordEvent(new CartItemAdded(
            $this->id,
            $itemId,
            $productId->value(),
            $quantity->value(),
            $unitPrice->amountInMinorUnits(),
            $unitPrice->currency(),
            new \DateTimeImmutable()
        ));
    }

    public function updateItem(ProductId $productId, int $quantity): void
    {
        $this->assertNotConverted();
        if ($quantity < 1) {
            $this->removeItem($productId);
            return;
        }
        foreach ($this->items as $i => $item) {
            if ($item->productId()->equals($productId)) {
                $this->items[$i] = $item->withQuantity($quantity);
                $this->recalculateTotal();
                $this->touchUpdatedAt();
                $this->recordEvent(new CartItemUpdated(
                    $this->id,
                    $productId->value(),
                    $quantity,
                    new \DateTimeImmutable()
                ));
                return;
            }
        }
    }

    public function removeItem(ProductId $productId): void
    {
        $this->assertNotConverted();
        $before = count($this->items);
        $this->items = array_values(array_filter(
            $this->items,
            fn (CartItem $item) => !$item->productId()->equals($productId)
        ));
        if (count($this->items) < $before) {
            $this->recalculateTotal();
            $this->touchUpdatedAt();
            $this->recordEvent(new CartItemRemoved($this->id, $productId->value(), new \DateTimeImmutable()));
        }
    }

    public function clear(): void
    {
        $this->assertNotConverted();
        $this->items = [];
        $this->totalAmount = Money::fromMinorUnits(0, $this->totalAmount->currency());
        $this->touchUpdatedAt();
        $this->recordEvent(new CartCleared($this->id, new \DateTimeImmutable()));
    }

    public function markConverted(string $orderId): void
    {
        if ($this->status->isConverted()) {
            throw CartAlreadyConvertedException::forCart($this->id->value());
        }
        $this->status = CartStatus::converted();
        $this->touchUpdatedAt();
        $this->recordEvent(new CartConverted($this->id, $orderId, new \DateTimeImmutable()));
    }

    public function markAbandoned(): void
    {
        $this->assertNotConverted();
        $this->status = CartStatus::abandoned();
        $this->touchUpdatedAt();
        $this->recordEvent(new CartAbandoned($this->id, new \DateTimeImmutable()));
    }

    public function calculateTotal(): Money
    {
        return $this->totalAmount;
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

    public function id(): CartId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerEmail(): ?CustomerEmail
    {
        return $this->customerEmail;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function status(): CartStatus
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
     * @return list<CartItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    private function assertNotConverted(): void
    {
        if ($this->status->isConverted()) {
            throw CartAlreadyConvertedException::forCart($this->id->value());
        }
    }

    private function recalculateTotal(): void
    {
        $currency = $this->totalAmount->currency();
        $totalMinor = 0;
        foreach ($this->items as $item) {
            $totalMinor += $item->totalPrice()->amountInMinorUnits();
            $currency = $item->totalPrice()->currency();
        }
        $this->totalAmount = Money::fromMinorUnits($totalMinor, $currency);
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
