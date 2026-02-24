<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Repositories;

use App\Modules\Payments\Domain\Entities\Payment;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;

interface PaymentRepository
{
    public function save(Payment $payment): void;

    public function findById(PaymentId $id): ?Payment;

    /**
     * @return list<Payment>
     */
    public function findByOrderId(OrderId $orderId): array;

    public function delete(Payment $payment): void;
}
