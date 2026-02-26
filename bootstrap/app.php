<?php

use App\Http\Middleware\CheckTenantFeature;
use App\Http\Middleware\EnsureSystemNotReadOnly;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureTenantSubscriptionIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureSystemNotReadOnly::class);
        // Required by Tenancy UniversalRoutes: empty group used as a flag so routes work on central & tenant
        $middleware->group('universal', []);
        $middleware->alias([
            'feature' => CheckTenantFeature::class,
            'subscription.active' => EnsureTenantSubscriptionIsActive::class,
            'tenant.not_suspended' => EnsureTenantNotSuspended::class,
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
