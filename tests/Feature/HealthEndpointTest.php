<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('health endpoint returns ok when all services available', function (): void {
    $response = $this->getJson('/health');
    $response->assertOk();
    $response->assertJsonStructure([
        'status',
        'database',
        'redis',
        'queue',
    ]);
    expect($response->json('status'))->toBeIn(['ok', 'degraded']);
    expect($response->json('database'))->toBeIn(['connected', 'disconnected']);
    expect($response->json('redis'))->toBeIn(['connected', 'disconnected']);
    expect($response->json('queue'))->toBeIn(['ok', 'error']);
})->group('health');

test('health returns json', function (): void {
    $response = $this->get('/health');
    $response->assertHeader('Content-Type', 'application/json');
})->group('health');
