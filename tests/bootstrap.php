<?php

/**
 * Test bootstrap for multi-database tenancy.
 * @see https://tenancyforlaravel.com/docs/v3/testing
 *
 * With multi-database tenancy it's not possible to use :memory: SQLite or
 * RefreshDatabase against the default connection due to DB switching.
 * Use a file-based central DB so tenant DBs are separate files.
 */
require __DIR__ . '/../vendor/autoload.php';

$dir = __DIR__ . '/../database';
$path = $dir . '/testing.sqlite';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}
if (! file_exists($path)) {
    touch($path);
}
$resolved = realpath($path);
if ($resolved !== false) {
    putenv('DB_DATABASE=' . $resolved);
    $_ENV['DB_DATABASE'] = $resolved;
    $_SERVER['DB_DATABASE'] = $resolved;
}
