<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Resolves the database name (or connection identifier) for a tenant.
 * Extension point for future tenant sharding: range-based, hash-based, or region-based.
 *
 * Current implementation: Stancl tenancy uses prefix+suffix (e.g. tenant{id}).
 * Do NOT implement sharding in this interface; only allow swapping the resolver
 * when sharding is introduced.
 */
interface TenantDatabaseResolver
{
    /**
     * Resolve the tenant's database name for the given connection driver.
     * Used when creating or connecting to tenant databases.
     *
     * @param  string  $tenantId  Tenant identifier (e.g. UUID).
     * @param  string  $driver  Database driver (mysql, pgsql, sqlite).
     * @return string Database name or connection key (e.g. "tenant_abc123" or "shard_2").
     */
    public function databaseNameForTenant(string $tenantId, string $driver = 'mysql'): string;
}
