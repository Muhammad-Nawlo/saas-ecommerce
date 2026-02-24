<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Landlord\Models\Tenant;
use App\Modules\Shared\Domain\Exceptions\TenantSuspendedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access for tenants whose status is suspended.
 * Run after tenancy is initialized so tenant() is available.
 */
class EnsureTenantNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return $next($request);
        }

        $status = $tenant->status ?? 'active';
        if (strtolower($status) === 'suspended') {
            return response()->json(
                ['message' => (TenantSuspendedException::forTenant((string) $tenantId))->getMessage()],
                403
            );
        }

        return $next($request);
    }
}
