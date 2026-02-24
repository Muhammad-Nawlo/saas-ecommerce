<?php

declare(strict_types=1);

namespace App\Landlord\Models;

use App\Modules\Shared\Infrastructure\Audit\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Landlord audit log. Central DB only. Read-only for Filament.
 */
class LandlordAuditLog extends AuditLog
{
    protected $connection;

    protected $table = 'landlord_audit_logs';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
    }

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }
        return $query->where('tenant_id', $tenantId);
    }
}
