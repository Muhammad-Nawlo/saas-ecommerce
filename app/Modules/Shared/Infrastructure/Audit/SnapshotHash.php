<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

/**
 * Tamper detection: SHA-256 hash of immutable snapshot data.
 * Used for FinancialOrder, Invoice, Payment. Do not auto-correct; only detect.
 */
final class SnapshotHash
{
    public static function hash(array $data): string
    {
        ksort($data);
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $payload);
    }
}
