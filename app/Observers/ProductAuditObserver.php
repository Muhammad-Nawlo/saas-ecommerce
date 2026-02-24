<?php

declare(strict_types=1);

namespace App\Observers;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class ProductAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(ProductModel $model): void
    {
        $this->logger->logTenantAction(
            'created',
            "Product created: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }

    public function updated(ProductModel $model): void
    {
        // Log in updating() so diff is correct (getOriginal() has old values).
    }

    public function updating(ProductModel $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logTenantAction(
                'updated',
                "Product updated: {$model->name}",
                $model,
                ['changes' => $changes],
            );
        }
    }

    public function deleted(ProductModel $model): void
    {
        $this->logger->logTenantAction(
            'deleted',
            "Product deleted: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }
}
