<?php

declare(strict_types=1);

namespace App\Observers;

use App\Landlord\Models\Feature;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class FeatureAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(Feature $model): void
    {
        $this->logger->logLandlordAction(
            'created',
            "Feature created: {$model->code}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }

    public function updated(Feature $model): void
    {
    }

    public function updating(Feature $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logLandlordAction(
                'updated',
                "Feature updated: {$model->code}",
                $model,
                ['changes' => $changes],
            );
        }
    }

    public function deleted(Feature $model): void
    {
        $this->logger->logLandlordAction(
            'deleted',
            "Feature deleted: {$model->code}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
        );
    }
}
