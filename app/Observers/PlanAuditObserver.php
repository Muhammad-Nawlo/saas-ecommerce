<?php

declare(strict_types=1);

namespace App\Observers;

use App\Landlord\Models\Plan;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class PlanAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(Plan $model): void
    {
        $this->logger->logLandlordAction(
            'created',
            "Plan created: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }

    public function updated(Plan $model): void
    {
    }

    public function updating(Plan $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logLandlordAction(
                'updated',
                "Plan updated: {$model->name}",
                $model,
                ['changes' => $changes],
            );
        }
    }

    public function deleted(Plan $model): void
    {
        $this->logger->logLandlordAction(
            'deleted',
            "Plan deleted: {$model->name}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }
}
