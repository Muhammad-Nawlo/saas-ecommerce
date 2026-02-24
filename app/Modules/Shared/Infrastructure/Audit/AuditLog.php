<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log entry. Connection and table are set by AuditLogger before save.
 * Tenant logs: tenant DB, tenant_audit_logs.
 * Landlord logs: central DB, landlord_audit_logs.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'model_type',
        'model_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
        'tenant_id',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
