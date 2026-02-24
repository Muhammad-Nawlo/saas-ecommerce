<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

use App\Jobs\LogAuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Audit logger. Dispatches to queue so logging never blocks the request.
 * Use logTenantAction() in tenant context, logLandlordAction() in landlord context.
 */
final class AuditLogger
{
    public function log(
        string $eventType,
        string $description,
        ?Model $model = null,
        array $properties = [],
    ): void {
        $connection = $this->resolveConnection();
        $table = $this->resolveTable();
        $attributes = [
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'model_type' => $model ? $model->getMorphClass() : '',
            'model_id' => $model ? (string) $model->getKey() : null,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];
        if ($table === config('audit.landlord_table')) {
            $attributes['tenant_id'] = tenant('id');
        }
        LogAuditEntry::dispatch($connection, $table, $attributes, tenant('id'))->onQueue(config('audit.queue', 'low'));
    }

    /** Log an action in tenant context. Stores in tenant DB only. */
    public function logTenantAction(
        string $eventType,
        string $description,
        ?Model $model = null,
        array $properties = [],
    ): void {
        $this->log($eventType, $description, $model, $properties);
    }

    /** Log an action in landlord context. Stores in central DB only. */
    public function logLandlordAction(
        string $eventType,
        string $description,
        ?Model $model = null,
        array $properties = [],
        ?string $tenantId = null,
    ): void {
        $connection = config('tenancy.database.central_connection', config('database.default'));
        $table = config('audit.landlord_table');
        $attributes = [
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'model_type' => $model ? $model->getMorphClass() : '',
            'model_id' => $model ? (string) $model->getKey() : null,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'tenant_id' => $tenantId ?? tenant('id'),
        ];
        LogAuditEntry::dispatch($connection, $table, $attributes, $tenantId)->onQueue(config('audit.queue', 'low'));
    }

    private function resolveConnection(): string
    {
        if (tenant() !== null) {
            return config('database.default');
        }
        return config('tenancy.database.central_connection', config('database.default'));
    }

    private function resolveTable(): string
    {
        if (tenant() !== null) {
            return config('audit.tenant_table');
        }
        return config('audit.landlord_table');
    }
}
