<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Landlord\Models\Tenant;
use App\Modules\Payments\Domain\Events\PaymentSucceeded;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Ensures financial-critical sync listeners: failures surface (no silent swallow).
 */
test('sync financial listener failure surfaces', function (): void {
    $tenant = Tenant::create(['name' => 'Listener Fail', 'data' => []]);
    $tenant->run(function (): void {
        Artisan::call('migrate', ['--path' => database_path('migrations/tenant'), '--force' => true]);
    });
    tenancy()->initialize($tenant);

    $thrown = new \RuntimeException('Intentional listener failure');
    Event::listen(PaymentSucceeded::class, function () use ($thrown): void {
        throw $thrown;
    });

    $event = new PaymentSucceeded(
        PaymentId::fromString(\Illuminate\Support\Str::uuid()->toString()),
        \Illuminate\Support\Str::uuid()->toString(),
        new \DateTimeImmutable()
    );

    expect(fn () => event($event))->toThrow(\RuntimeException::class, 'Intentional listener failure');

    tenancy()->end();
})->group('financial');
