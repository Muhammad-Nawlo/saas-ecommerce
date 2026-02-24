<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to Landlord Filament panel to users with super_admin role.
 * Use in panel authMiddleware. Aborts 403 if user is not super_admin.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('filament.landlord.auth.login');
        }

        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Only platform administrators can access this panel.');
        }

        return $next($request);
    }

    private function isSuperAdmin($user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return $user->is_super_admin ?? false;
    }
}
