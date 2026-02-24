<?php

declare(strict_types=1);

namespace App\Observers;

use App\Modules\Inventory\Infrastructure\Persistence\StockItemModel;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class InventoryAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(StockItemModel $model): void
    {
        $this->logger->logTenantAction(
            'created',
            'Inventory record created',
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }

    public function updated(StockItemModel $model): void
    {
    }

    public function updating(StockItemModel $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logTenantAction(
                'updated',
                'Inventory record updated',
                $model,
                ['changes' => $changes],
            );
        }
    }

    public function deleted(StockItemModel $model): void
    {
        $this->logger->logTenantAction(
            'deleted',
            'Inventory record deleted',
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }
}
