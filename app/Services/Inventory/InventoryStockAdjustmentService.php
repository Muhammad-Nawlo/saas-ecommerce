<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Models\Inventory\InventoryLocationStock;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual stock adjustment. Always logs movement and enforces non-negative quantity/reserved.
 */
final class InventoryStockAdjustmentService
{
    public function __construct(
        private InventoryMovementLogger $movementLogger,
        private AuditLogger $auditLogger,
    ) {}

    public function adjust(
        string $productId,
        string $locationId,
        int $delta,
        string $reason = 'Manual adjustment',
    ): InventoryLocationStock {
        if ($delta === 0) {
            throw new InvalidArgumentException('Adjustment delta cannot be zero.');
        }
        return DB::transaction(function () use ($productId, $locationId, $delta, $reason): InventoryLocationStock {
            $stock = InventoryLocationStock::where('product_id', $productId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();
            if ($stock === null) {
                if ($delta < 0) {
                    throw new InvalidArgumentException('Cannot decrease stock: no stock record exists.');
                }
                $stock = InventoryLocationStock::create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
            $newQuantity = $stock->quantity + $delta;
            if ($newQuantity < 0) {
                throw new InvalidArgumentException('Adjustment would result in negative quantity.');
            }
            if ($stock->reserved_quantity > $newQuantity) {
                throw new InvalidArgumentException('Reserved quantity cannot exceed quantity after adjustment.');
            }
            $stock->quantity = $newQuantity;
            $stock->save();
            $type = $delta > 0 ? InventoryMovement::TYPE_INCREASE : InventoryMovement::TYPE_DECREASE;
            $this->movementLogger->log(
                $productId,
                $locationId,
                $type,
                abs($delta),
                'manual',
                null,
                $reason,
            );
            $this->auditLogger->logTenantAction(
                'stock_adjusted',
                "Stock adjusted: product {$productId}, location {$locationId}, delta {$delta}",
                $stock,
                ['delta' => $delta, 'reason' => $reason, 'actor_id' => auth()->id()],
            );
            if ($stock->low_stock_threshold !== null && ($stock->quantity - $stock->reserved_quantity) <= $stock->low_stock_threshold) {
                event(new \App\Events\Inventory\LowStockDetected($stock, $stock->low_stock_threshold));
            }
            return $stock;
        });
    }

    public function setLowStockThreshold(InventoryLocationStock $stock, ?int $threshold): void
    {
        if ($threshold !== null && $threshold < 0) {
            throw new InvalidArgumentException('Low stock threshold cannot be negative.');
        }
        $stock->update(['low_stock_threshold' => $threshold]);
    }
}
