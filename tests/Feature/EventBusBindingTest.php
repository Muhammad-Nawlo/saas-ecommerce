<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Messaging\LaravelEventBus;

test('EventBus interface is bound to LaravelEventBus', function (): void {
    $eventBus = app(EventBus::class);
    expect($eventBus)->toBeInstanceOf(LaravelEventBus::class);
})->group('security');

test('EventBus is a singleton', function (): void {
    $first = app(EventBus::class);
    $second = app(EventBus::class);
    expect($first)->toBe($second);
})->group('security');
