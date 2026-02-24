<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Landlord\Models\Subscription;
use App\Modules\Shared\Domain\Exceptions\TenantSuspendedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant('id');
        if ($tenantId === null) {
            return response()->json(['message' => 'Tenant context required'], 403);
        }

        $connection = config('tenancy.database.central_connection', config('database.default'));
        $subscription = Subscription::on($connection)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return response()->json(['message' => (TenantSuspendedException::forTenant((string) $tenantId))->getMessage()], 403);
        }

        $allowed = ['active', 'trialing'];
        if (!in_array($subscription->status, $allowed, true)) {
            return response()->json(['message' => (TenantSuspendedException::forTenant((string) $tenantId))->getMessage()], 403);
        }

        return $next($request);
    }
}
