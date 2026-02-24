<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role check. Use for tenant or landlord routes.
 * Example: ->middleware(['auth', 'role:owner,manager'])
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'hasRole') && $user->hasAnyRole($roles)) {
            return $next($request);
        }

        abort(403, 'You do not have the required role.');
    }
}
