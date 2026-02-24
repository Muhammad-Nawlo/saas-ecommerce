<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Models\Inventory\InventoryLocationStock;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryTransfer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transfer stock between locations. Decrease source, increase destination, log movements.
 */
final class InventoryTransferService
{
    public function __construct(
        private InventoryLocationService $locationService,
        private InventoryMovementLogger $movementLogger,
        private AuditLogger $auditLogger,
    ) {}

    public function transfer(string $productId, string $fromLocationId, string $toLocationId, int $quantity): InventoryTransfer
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Transfer quantity must be positive.');
        }
        $tenantId = tenant('id');
        if ($tenantId !== null && !$this->locationService->canTransfer((string) $tenantId)) {
            throw new InvalidArgumentException('Multi-location transfers are not enabled for this tenant.');
        }
        if ($fromLocationId === $toLocationId) {
            throw new InvalidArgumentException('Source and destination must differ.');
        }
        $transfer = null;
        DB::transaction(function () use ($productId, $fromLocationId, $toLocationId, $quantity, &$transfer): void {
            $fromStock = InventoryLocationStock::where('product_id', $productId)
                ->where('location_id', $fromLocationId)
                ->lockForUpdate()
                ->first();
            if ($fromStock === null) {
                throw new InvalidArgumentException("No stock record for product {$productId} at source location.");
            }
            $available = $fromStock->quantity - $fromStock->reserved_quantity;
            if ($available < $quantity) {
                throw new InvalidArgumentException(
                    "Insufficient available stock to transfer: have {$available}, need {$quantity}."
                );
            }
            $fromStock->quantity -= $quantity;
            $fromStock->save();
            $toStock = InventoryLocationStock::where('product_id', $productId)
                ->where('location_id', $toLocationId)
                ->lockForUpdate()
                ->first();
            if ($toStock === null) {
                $toStock = InventoryLocationStock::create([
                    'product_id' => $productId,
                    'location_id' => $toLocationId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
            $toStock->quantity += $quantity;
            $toStock->save();
            $transfer = InventoryTransfer::create([
                'product_id' => $productId,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'status' => InventoryTransfer::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            $this->movementLogger->log(
                $productId,
                $fromLocationId,
                InventoryMovement::TYPE_TRANSFER_OUT,
                $quantity,
                'transfer',
                $transfer->id,
                'Transfer out',
            );
            $this->movementLogger->log(
                $productId,
                $toLocationId,
                InventoryMovement::TYPE_TRANSFER_IN,
                $quantity,
                'transfer',
                $transfer->id,
                'Transfer in',
            );
        });
        $this->auditLogger->logTenantAction('transfer_completed', 'Transfer completed: ' . $transfer->id, $transfer, ['actor_id' => auth()->id()]);
        return $transfer;
    }

    public function cancel(InventoryTransfer $transfer): void
    {
        if ($transfer->status !== InventoryTransfer::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending transfers can be cancelled.');
        }
        $transfer->update(['status' => InventoryTransfer::STATUS_CANCELLED]);
    }
}
