<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\DTOs;

final readonly class CartSnapshotDTO
{
    /**
     * @param list<CartSnapshotItemDTO> $items
     */
    public function __construct(
        public string $cartId,
        public string $tenantId,
        public ?string $customerEmail,
        public array $items,
        public int $totalAmountMinorUnits,
        public string $currency
    ) {
    }

    /**
     * @return array<int, array{product_id: string, quantity: int}>
     */
    public function itemsForStock(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = ['product_id' => $item->productId, 'quantity' => $item->quantity];
        }
        return $result;
    }

    /**
     * @return array<int, array{product_id: string, quantity: int, unit_price_minor_units: int, currency: string}>
     */
    public function itemsForOrder(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = [
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
                'unit_price_minor_units' => $item->unitPriceMinorUnits,
                'currency' => $item->currency,
            ];
        }
        return $result;
    }
}
