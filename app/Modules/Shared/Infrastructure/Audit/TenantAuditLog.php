<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant audit log. Uses default connection (tenant when in tenant context).
 * Read-only for Filament.
 */
class TenantAuditLog extends AuditLog
{
    protected $table = 'tenant_audit_logs';

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }
}
