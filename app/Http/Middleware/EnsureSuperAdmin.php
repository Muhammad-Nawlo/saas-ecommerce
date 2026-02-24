<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\LandlordRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to Landlord Filament panel to users with a landlord role.
 * Aborts 403 if user has no landlord role. Policy checks restrict actions per role.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('filament.landlord.auth.login');
        }

        if (!$this->hasLandlordRole($user)) {
            abort(403, 'Only platform administrators can access this panel.');
        }

        return $next($request);
    }

    private function hasLandlordRole($user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole(LandlordRole::SuperAdmin->value)
                || $user->hasRole(LandlordRole::SupportAgent->value)
                || $user->hasRole(LandlordRole::FinanceAdmin->value);
        }

        return (bool) ($user->is_super_admin ?? false);
    }
}
