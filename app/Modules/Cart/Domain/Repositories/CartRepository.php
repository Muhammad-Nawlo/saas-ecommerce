<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain\Repositories;

use App\Modules\Cart\Domain\Entities\Cart;
use App\Modules\Cart\Domain\ValueObjects\CartId;

interface CartRepository
{
    public function save(Cart $cart): void;

    public function findById(CartId $id): ?Cart;

    public function findActiveByCustomer(string $email): ?Cart;

    public function findActiveBySession(string $sessionId): ?Cart;

    public function delete(Cart $cart): void;
}
