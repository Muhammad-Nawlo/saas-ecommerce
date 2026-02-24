<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Entities;

use App\Modules\Cart\Domain\ValueObjects\CartItemId;
use App\Modules\Cart\Domain\ValueObjects\ProductId;
use App\Modules\Cart\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\Money;

final class CartItem
{
    private function __construct(
        private CartItemId $id,
        private ProductId $productId,
        private int $quantity,
        private Money $unitPrice,
        private Money $totalPrice
    ) {
    }

    public static function create(
        CartItemId $id,
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

    public function id(): CartItemId
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

    public function withQuantity(int $newQuantity): self
    {
        if ($newQuantity < 1) {
            throw BusinessRuleViolation::because('Quantity must be positive');
        }
        $totalMinor = $this->unitPrice->amountInMinorUnits() * $newQuantity;
        $totalPrice = Money::fromMinorUnits($totalMinor, $this->unitPrice->currency());
        return new self(
            $this->id,
            $this->productId,
            $newQuantity,
            $this->unitPrice,
            $totalPrice
        );
    }
}
