<?php

declare(strict_types=1);

namespace App\Observers;

use App\Landlord\Models\Subscription;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class SubscriptionAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(Subscription $model): void
    {
        $this->logger->logLandlordAction(
            'created',
            "Subscription created for tenant: {$model->tenant_id}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            (string) $model->tenant_id,
        );
    }

    public function updated(Subscription $model): void
    {
    }

    public function updating(Subscription $model): void
    {
        $changes = AuditAttributeFilter::diff($model);
        if ($changes !== []) {
            $this->logger->logLandlordAction(
                'updated',
                "Subscription updated for tenant: {$model->tenant_id}",
                $model,
                ['changes' => $changes],
                (string) $model->tenant_id,
            );
        }
    }

    public function deleted(Subscription $model): void
    {
        $this->logger->logLandlordAction(
            'deleted',
            "Subscription deleted for tenant: {$model->tenant_id}",
            $model,
            ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            (string) $model->tenant_id,
        );
    }
}
