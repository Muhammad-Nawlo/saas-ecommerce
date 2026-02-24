<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Modules\Shared\Infrastructure\Audit\AuditLogger;
use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryLocationStock;
use App\Models\Inventory\InventoryReservation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Allocate, reserve, release, confirm stock by location. Uses row locking to prevent overselling.
 */
final class InventoryAllocationService
{
    public function __construct(
        private InventoryLocationService $locationService,
        private InventoryMovementLogger $movementLogger,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Reserve stock for each order line from best-available location(s). Call when order is created.
     */
    public function allocateStock(OrderModel $order): void
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            throw new InvalidArgumentException('Tenant context required.');
        }
        $order->load('items');
        if ($order->items->isEmpty()) {
            return;
        }
        $defaultLocation = $this->locationService->getOrCreateDefaultLocation((string) $tenantId);
        DB::transaction(function () use ($order, $defaultLocation, $tenantId): void {
            foreach ($order->items as $item) {
                $this->reserveStockForOrder(
                    (string) $item->product_id,
                    (int) $item->quantity,
                    (string) $order->id,
                    (string) $tenantId,
                    $defaultLocation
                );
            }
        });
        $this->auditLogger->logTenantAction(
            'reservation_created',
            'Stock reserved for order: ' . $order->id,
            $order,
            ['order_id' => $order->id, 'actor_id' => auth()->id()],
        );
    }

    /**
     * Reserve quantity at a specific location (for order allocation).
     */
    public function reserveStock(string $productId, string $locationId, int $quantity, string $orderId, ?string $expiresAt = null): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }
        DB::transaction(function () use ($productId, $locationId, $quantity, $orderId, $expiresAt): void {
            $stock = InventoryLocationStock::where('product_id', $productId)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();
            if ($stock === null) {
                $stock = InventoryLocationStock::create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
            $available = $stock->quantity - $stock->reserved_quantity;
            if ($available < $quantity) {
                throw new InvalidArgumentException(
                    "Insufficient stock for product {$productId} at location {$locationId}: required {$quantity}, available {$available}."
                );
            }
            $stock->reserved_quantity += $quantity;
            $stock->save();
            InventoryReservation::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
            ]);
            $this->movementLogger->log(
                $productId,
                $locationId,
                \App\Models\Inventory\InventoryMovement::TYPE_RESERVE,
                $quantity,
                'order',
                $orderId,
                'Order reservation',
            );
        });
    }

    /**
     * Release all reservations for an order. Call when order is cancelled.
     */
    public function releaseReservation(OrderModel $order): void
    {
        $order->load('items');
        DB::transaction(function () use ($order): void {
            $reservations = InventoryReservation::where('order_id', $order->id)->lockForUpdate()->get();
            foreach ($reservations as $res) {
                $stock = InventoryLocationStock::where('product_id', $res->product_id)
                    ->where('location_id', $res->location_id)
                    ->lockForUpdate()
                    ->first();
                if ($stock !== null) {
                    $stock->reserved_quantity = max(0, $stock->reserved_quantity - $res->quantity);
                    $stock->save();
                }
                $this->movementLogger->log(
                    $res->product_id,
                    $res->location_id,
                    \App\Models\Inventory\InventoryMovement::TYPE_RELEASE,
                    $res->quantity,
                    'order',
                    $order->id,
                    'Order cancelled',
                );
                $res->delete();
            }
        });
        $this->auditLogger->logTenantAction(
            'reservation_released',
            'Reservations released for order: ' . $order->id,
            $order,
            ['order_id' => $order->id, 'actor_id' => auth()->id()],
        );
    }

    /**
     * Confirm reservation: permanently decrease quantity and remove reservation. Call when order is paid.
     */
    public function confirmReservation(OrderModel $order): void
    {
        $order->load('items');
        DB::transaction(function () use ($order): void {
            $reservations = InventoryReservation::where('order_id', $order->id)->lockForUpdate()->get();
            foreach ($reservations as $res) {
                $stock = InventoryLocationStock::where('product_id', $res->product_id)
                    ->where('location_id', $res->location_id)
                    ->lockForUpdate()
                    ->first();
                if ($stock === null) {
                    $res->delete();
                    continue;
                }
                $stock->quantity = max(0, $stock->quantity - $res->quantity);
                $stock->reserved_quantity = max(0, $stock->reserved_quantity - $res->quantity);
                $stock->save();
                $this->movementLogger->log(
                    $res->product_id,
                    $res->location_id,
                    \App\Models\Inventory\InventoryMovement::TYPE_DECREASE,
                    $res->quantity,
                    'order',
                    $order->id,
                    'Order paid',
                );
                $res->delete();
            }
        });
        $this->auditLogger->logTenantAction(
            'reservation_confirmed',
            'Reservations confirmed (stock decreased) for order: ' . $order->id,
            $order,
            ['order_id' => $order->id, 'actor_id' => auth()->id()],
        );
    }

    /**
     * Get total available quantity for a product across all locations (or default only).
     */
    public function getAvailableQuantity(string $productId, ?string $tenantId = null): int
    {
        $tenantId = $tenantId ?? (string) tenant('id');
        $location = $this->locationService->getOrCreateDefaultLocation($tenantId);
        $stock = InventoryLocationStock::where('product_id', $productId)
            ->where('location_id', $location->id)
            ->first();
        if ($stock === null) {
            return 0;
        }
        return $stock->availableQuantity();
    }

    private function reserveStockForOrder(
        string $productId,
        int $quantity,
        string $orderId,
        string $tenantId,
        InventoryLocation $defaultLocation,
    ): void {
        if ($this->locationService->canCreateMoreLocations($tenantId)) {
            $locationIds = InventoryLocationStock::where('product_id', $productId)
                ->whereIn('location_id', InventoryLocation::forTenant($tenantId)->active()->pluck('id'))
                ->get()
                ->sortByDesc(fn ($s) => $s->quantity - $s->reserved_quantity)
                ->pluck('location_id')
                ->all();
            if (empty($locationIds)) {
                $locationIds = InventoryLocation::forTenant($tenantId)->active()->pluck('id')->all();
            }
        } else {
            $locationIds = [$defaultLocation->id];
        }
        $remaining = $quantity;
        foreach ($locationIds as $locId) {
            if ($remaining <= 0) {
                break;
            }
            $stock = InventoryLocationStock::where('product_id', $productId)
                ->where('location_id', $locId)
                ->lockForUpdate()
                ->first();
            if ($stock === null) {
                $stock = InventoryLocationStock::create([
                    'product_id' => $productId,
                    'location_id' => $locId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
            $available = $stock->quantity - $stock->reserved_quantity;
            $take = min($remaining, $available);
            if ($take <= 0) {
                continue;
            }
            $stock->reserved_quantity += $take;
            $stock->save();
            InventoryReservation::create([
                'product_id' => $productId,
                'location_id' => $locId,
                'order_id' => $orderId,
                'quantity' => $take,
            ]);
            $this->movementLogger->log(
                $productId,
                $locId,
                \App\Models\Inventory\InventoryMovement::TYPE_RESERVE,
                $take,
                'order',
                $orderId,
                'Order allocation',
            );
            $remaining -= $take;
        }
        if ($remaining > 0) {
            throw new InvalidArgumentException(
                "Insufficient stock for product {$productId}: required {$quantity}, could allocate " . ($quantity - $remaining) . '.'
            );
        }
    }
}
