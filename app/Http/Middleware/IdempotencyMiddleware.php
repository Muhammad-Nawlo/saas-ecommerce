<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    private const string CACHE_PREFIX = 'idempotency:';
    private const int TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || trim($key) === '') {
            return $next($request);
        }
        $key = trim($key);
        if (strlen($key) > 128) {
            $key = substr($key, 0, 128);
        }
        $tenantId = tenant('id');
        $cacheKey = self::CACHE_PREFIX . ($tenantId ?? 'global') . ':' . $key . ':' . $request->path();

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached['body'], $cached['status'], $cached['headers'] ?? []);
        }

        $response = $next($request);
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $body = $response->getContent();
            Cache::put($cacheKey, [
                'body' => json_decode($body, true) ?? [],
                'status' => $status,
                'headers' => [],
            ], self::TTL_SECONDS);
        }
        return $response;
    }
}
