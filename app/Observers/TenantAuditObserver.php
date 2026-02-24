<?php

declare(strict_types=1);

namespace App\Observers;

use App\Landlord\Models\Tenant;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class TenantAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(Tenant $model): void
    {
        $this->logger->logLandlordAction(
            'created',
            "Tenant created: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            (string) $model->id,
        );
    }

    public function updated(Tenant $model): void
    {
    }

    public function updating(Tenant $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logLandlordAction(
                'updated',
                "Tenant updated: {$model->name}",
                $model,
                ['changes' => $changes],
                (string) $model->id,
            );
        }
    }

    public function deleted(Tenant $model): void
    {
        $this->logger->logLandlordAction(
            'deleted',
            "Tenant deleted: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            (string) $model->id,
        );
    }
}
