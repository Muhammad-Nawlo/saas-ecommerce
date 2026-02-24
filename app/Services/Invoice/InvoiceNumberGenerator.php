<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use Illuminate\Support\Facades\DB;

/**
 * Format: INV-YYYY-XXXX. Unique per tenant. Counter resets yearly.
 * Uses row locking to prevent duplicate numbers.
 */
final class InvoiceNumberGenerator
{
    public function generate(string $tenantId): string
    {
        $year = (int) date('Y');
        return DB::transaction(function () use ($tenantId, $year): string {
            $row = DB::table('invoice_number_sequence')
                ->where('tenant_id', $tenantId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('invoice_number_sequence')->insert([
                    'tenant_id' => $tenantId,
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $next = 1;
            } else {
                $next = $row->last_number + 1;
                DB::table('invoice_number_sequence')
                    ->where('tenant_id', $tenantId)
                    ->where('year', $year)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            }

            return sprintf('INV-%d-%04d', $year, $next);
        });
    }
}
