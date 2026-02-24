<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access for suspended tenants. Use on Tenant Filament panel.
 * If tenant.status !== 'active': logout and redirect to billing with message.
 */
class CheckTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();
        if ($tenant === null) {
            return $next($request);
        }

        $status = $tenant->status ?? 'active';
        if (strtolower((string) $status) === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('filament.tenant.auth.login')
                ->with('error', 'Your store has been suspended. Please contact billing to restore access.');
        }

        return $next($request);
    }
}
