<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use Illuminate\Support\Facades\Request;

/**
 * Append-only movement log. Every stock change must go through this.
 * Includes actor_id, IP, timestamp, reason in meta.
 */
final class InventoryMovementLogger
{
    public function log(
        string $productId,
        string $locationId,
        string $type,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $reason = null,
        array $extraMeta = [],
    ): InventoryMovement {
        $meta = array_merge($extraMeta, [
            'actor_id' => auth()->id(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => now()->toIso8601String(),
            'reason' => $reason,
        ]);
        return InventoryMovement::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'meta' => $meta,
        ]);
    }
}
