<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Repositories;

use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\ValueObjects\OrderId;

interface OrderRepository
{
    public function save(Order $order, ?string $customerId = null): void;

    public function findById(OrderId $id): ?Order;

    public function delete(Order $order): void;
}
