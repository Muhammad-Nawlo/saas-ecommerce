<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Ledger\Ledger;
use App\Models\Ledger\LedgerAccount;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Double-entry ledger: create balanced transactions. Entries are immutable.
 *
 * Invariants:
 * - Sum of debits MUST equal sum of credits for each transaction.
 * - FinancialOrder acts as source reference for payment/refund transactions.
 */
final class LedgerService
{
    /**
     * Get or create ledger for tenant with default accounts (Revenue, Tax payable, Cash, Refund liability, etc.).
     */
    public function getOrCreateLedgerForTenant(string $tenantId, string $currency = 'USD'): Ledger
    {
        $ledger = Ledger::where('tenant_id', $tenantId)->first();
        if ($ledger !== null) {
            return $ledger;
        }
        return DB::transaction(function () use ($tenantId, $currency): Ledger {
            $ledger = Ledger::create([
                'tenant_id' => $tenantId,
                'name' => 'Default',
                'currency' => $currency,
                'is_active' => true,
            ]);
            $this->ensureDefaultAccounts($ledger);
            return $ledger;
        });
    }

    public function ensureDefaultAccounts(Ledger $ledger): void
    {
        $defaults = [
            ['code' => 'REV', 'name' => 'Revenue', 'type' => LedgerAccount::TYPE_REVENUE],
            ['code' => 'TAX', 'name' => 'Tax Payable', 'type' => LedgerAccount::TYPE_TAX_PAYABLE],
            ['code' => 'CASH', 'name' => 'Cash', 'type' => LedgerAccount::TYPE_CASH],
            ['code' => 'AR', 'name' => 'Accounts Receivable', 'type' => LedgerAccount::TYPE_ACCOUNTS_RECEIVABLE],
            ['code' => 'REFUND', 'name' => 'Refund Liability', 'type' => LedgerAccount::TYPE_REFUND_LIABILITY],
        ];
        foreach ($defaults as $d) {
            if (LedgerAccount::where('ledger_id', $ledger->id)->where('code', $d['code'])->exists()) {
                continue;
            }
            LedgerAccount::create([
                'ledger_id' => $ledger->id,
                'code' => $d['code'],
                'name' => $d['name'],
                'type' => $d['type'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Create a balanced ledger transaction with entries.
     *
     * @param array<int, array{account_id: string, type: 'debit'|'credit', amount_cents: int, currency: string, memo?: string}> $entries
     */
    public function createTransaction(
        string $ledgerId,
        string $referenceType,
        ?string $referenceId,
        ?string $description,
        array $entries,
        ?\DateTimeInterface $transactionAt = null
    ): LedgerTransaction {
        if ($entries === []) {
            throw new InvalidArgumentException('Ledger transaction must have at least one entry.');
        }
        $this->validateBalanced($entries);

        return DB::transaction(function () use ($ledgerId, $referenceType, $referenceId, $description, $entries, $transactionAt): LedgerTransaction {
            $tx = LedgerTransaction::create([
                'ledger_id' => $ledgerId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'transaction_at' => $transactionAt ?? now(),
            ]);
            foreach ($entries as $e) {
                LedgerEntry::create([
                    'ledger_transaction_id' => $tx->id,
                    'ledger_account_id' => $e['account_id'],
                    'type' => $e['type'],
                    'amount_cents' => $e['amount_cents'],
                    'currency' => $e['currency'],
                    'memo' => $e['memo'] ?? null,
                ]);
            }
            return $tx->load('entries');
        });
    }

    /**
     * @param array<int, array{type: string, amount_cents: int}> $entries
     */
    public function validateBalanced(array $entries): void
    {
        $debits = 0;
        $credits = 0;
        foreach ($entries as $e) {
            $amount = (int) $e['amount_cents'];
            if ($amount < 0) {
                throw new InvalidArgumentException('Ledger entry amount must be non-negative.');
            }
            if (($e['type'] ?? '') === LedgerEntry::TYPE_DEBIT) {
                $debits += $amount;
            } elseif (($e['type'] ?? '') === LedgerEntry::TYPE_CREDIT) {
                $credits += $amount;
            } else {
                throw new InvalidArgumentException('Ledger entry type must be debit or credit.');
            }
        }
        if ($debits !== $credits) {
            throw new InvalidArgumentException(sprintf('Unbalanced transaction: debits=%d credits=%d', $debits, $credits));
        }
    }
}
