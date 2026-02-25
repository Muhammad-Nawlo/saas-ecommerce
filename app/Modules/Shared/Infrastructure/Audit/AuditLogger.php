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
        if ($table === config('audit.tenant_table')) {
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

    /**
     * Standardized audit payload for compliance: tenant_id, actor_id, entity_type, entity_id, event_type, before_state, after_state, timestamp.
     * Use for financial and lifecycle events. Merges into properties; model/event_type set at top level.
     */
    public function logStructuredTenantAction(
        string $eventType,
        string $description,
        ?Model $model = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        array $extra = [],
    ): void {
        $properties = array_merge([
            'tenant_id' => tenant('id'),
            'actor_id' => auth()->id(),
            'entity_type' => $model ? $model->getMorphClass() : '',
            'entity_id' => $model ? (string) $model->getKey() : null,
            'event_type' => $eventType,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'timestamp' => now()->toIso8601String(),
        ], $extra);
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

    /**
     * Standardized landlord audit: tenant_id, actor_id, entity_type, entity_id, event_type, before_state, after_state, timestamp.
     */
    public function logStructuredLandlordAction(
        string $eventType,
        string $description,
        ?Model $model = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        array $extra = [],
        ?string $tenantId = null,
    ): void {
        $properties = array_merge([
            'tenant_id' => $tenantId ?? tenant('id'),
            'actor_id' => auth()->id(),
            'entity_type' => $model ? $model->getMorphClass() : '',
            'entity_id' => $model ? (string) $model->getKey() : null,
            'event_type' => $eventType,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'timestamp' => now()->toIso8601String(),
        ], $extra);
        $this->logLandlordAction($eventType, $description, $model, $properties, $tenantId);
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
