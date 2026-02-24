<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Modules\Shared\Infrastructure\Audit\AuditAttributeFilter;
use App\Modules\Shared\Infrastructure\Audit\AuditLogger;

class UserAuditObserver
{
    public function __construct(
        private readonly AuditLogger $logger
    ) {}

    public function created(User $model): void
    {
        if (tenant() !== null) {
            $this->logger->logTenantAction(
                'created',
                "User created: {$model->email}",
                $model,
                ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            );
        }
    }

    public function updated(User $model): void
    {
    }

    public function updating(User $model): void
    {
        if (tenant() !== null) {
            $changes = AuditAttributeFilter::diff($model);
            if ($changes !== []) {
                $this->logger->logTenantAction(
                    'updated',
                    "User updated: {$model->email}",
                    $model,
                    ['changes' => $changes],
                );
            }
        }
    }

    public function deleted(User $model): void
    {
        if (tenant() !== null) {
            $this->logger->logTenantAction(
                'deleted',
                "User deleted: {$model->email}",
                $model,
                ['attributes' => AuditAttributeFilter::filter($model->getAttributes())],
            );
        }
    }
}
