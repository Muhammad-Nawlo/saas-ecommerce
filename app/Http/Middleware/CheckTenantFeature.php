<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Landlord\Services\FeatureResolver;
use App\Modules\Shared\Domain\Exceptions\FeatureNotAvailableException;
use App\Modules\Shared\Domain\Exceptions\NoActiveSubscriptionException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantFeature
{
    public function __construct(
        private FeatureResolver $featureResolver
    ) {
    }

    /**
     * @param  \Closure(Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        try {
            $hasFeature = $this->featureResolver->hasFeature($featureCode);
            if (!$hasFeature) {
                throw FeatureNotAvailableException::forFeature($featureCode);
            }
        } catch (NoActiveSubscriptionException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (FeatureNotAvailableException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
        return $next($request);
    }
}
