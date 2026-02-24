<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant DB model for activity log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $entity_type
 * @property string $entity_id
 * @property string $action
 * @property array|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['tenant_id', 'entity_type', 'entity_id', 'action', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}
