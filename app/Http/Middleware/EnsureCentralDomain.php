<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to central (landlord) routes. Use on Landlord Filament panel only.
 * If the request host is not in tenancy.central_domains, abort 404 so the panel never loads on tenant domains.
 */
final class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        if (! is_array($centralDomains) || ! in_array($host, $centralDomains, true)) {
            abort(404);
        }

        return $next($request);
    }
}
