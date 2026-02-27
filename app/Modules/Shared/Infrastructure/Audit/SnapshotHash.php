<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

/**
 * SnapshotHash
 *
 * Tamper detection: SHA-256 hash of immutable snapshot data. Used for FinancialOrder and Invoice
 * (setSnapshotHashFromCurrentState at lock time; verifySnapshotIntegrity to detect tampering).
 * Do not auto-correct on mismatch; only log and report. Stateless; no DB or tenant.
 */
final class SnapshotHash
{
    /**
     * Compute SHA-256 hash of payload. Keys are sorted before encoding for deterministic output.
     *
     * @param array<string, mixed> $data Snapshot/lock payload (e.g. id, totals, snapshot, locked_at).
     * @return string Hex hash.
     */
    public static function hash(array $data): string
    {
        ksort($data);
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $payload);
    }
}
