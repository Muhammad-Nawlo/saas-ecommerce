<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Entities;

use App\Modules\Orders\Domain\ValueObjects\OrderItemId;
use App\Modules\Orders\Domain\ValueObjects\ProductId;
use App\Modules\Orders\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\Money;

final class OrderItem
{
    private function __construct(
        private OrderItemId $id,
        private ProductId $productId,
        private int $quantity,
        private Money $unitPrice,
        private Money $totalPrice
    ) {
    }

    public static function create(
        OrderItemId $id,
        ProductId $productId,
        Quantity $quantity,
        Money $unitPrice
    ): self {
        if ($unitPrice->amountInMinorUnits() < 0) {
            throw BusinessRuleViolation::because('Unit price cannot be negative');
        }
        $q = $quantity->value();
        $unitMinor = $unitPrice->amountInMinorUnits();
        $totalMinor = $unitMinor * $q;
        $totalPrice = Money::fromMinorUnits($totalMinor, $unitPrice->currency());
        return new self($id, $productId, $q, $unitPrice, $totalPrice);
    }

    public function id(): OrderItemId
    {
        return $this->id;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function totalPrice(): Money
    {
        return $this->totalPrice;
    }
}
