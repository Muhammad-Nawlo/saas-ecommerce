<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Contracts;

use App\Modules\Checkout\Application\DTOs\CartSnapshotDTO;

interface CartService
{
    public function getActiveCart(string $cartId): ?CartSnapshotDTO;

    public function markCartConverted(string $cartId, string $orderId): void;
}
