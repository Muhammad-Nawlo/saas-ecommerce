<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When config('system.read_only') is true, block write requests (POST, PUT, PATCH, DELETE).
 * Used for maintenance windows and incident response. Does not alter normal behavior when false.
 */
final class EnsureSystemNotReadOnly
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('system.read_only', false)) {
            return $next($request);
        }
        if (in_array(strtoupper($request->method()), self::WRITE_METHODS, true)) {
            return response()->json([
                'message' => 'System is in read-only mode. Write operations are temporarily disabled.',
            ], 503);
        }
        return $next($request);
    }
}
