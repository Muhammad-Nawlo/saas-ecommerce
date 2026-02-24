<?php

declare(strict_types=1);

namespace App\Observers;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class OrderAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(OrderModel $model): void
    {
        $this->logger->logTenantAction(
            'created',
            "Order created: {$model->id}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }

    public function updated(OrderModel $model): void
    {
    }

    public function updating(OrderModel $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logTenantAction(
                'updated',
                "Order updated: {$model->id}",
                $model,
                ['changes' => $changes],
            );
        }
    }

    public function deleted(OrderModel $model): void
    {
        $this->logger->logTenantAction(
            'deleted',
            "Order deleted: {$model->id}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }
}
