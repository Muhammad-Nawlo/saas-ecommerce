<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('health endpoint returns ok when all services available', function (): void {
    $response = $this->getJson('/health');
    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'services' => [
            'db',
            'cache',
            'queue',
        ],
    ]);
    expect($response->json('status'))->toBeIn(['ok', 'degraded']);
    expect($response->json('services.db'))->toBeTrue();
    expect($response->json('services.cache'))->toBeTrue();
})->group('health');

test('health returns json', function (): void {
    $response = $this->get('/health');
    $response->assertHeader('Content-Type', 'application/json');
})->group('health');
