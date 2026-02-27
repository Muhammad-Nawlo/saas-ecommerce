<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LedgerEntry
 *
 * Single double-entry ledger line (debit or credit). Immutable once created. Part of LedgerTransaction;
 * reconciliation (FinancialReconciliationService) ensures debits == credits per transaction.
 * Amount in cents only (no float). Used for order paid (CASH debit, REV/TAX credit) and refund reversal (CASH credit, REV/TAX debit).
 *
 * Assumes tenant context. Tenant DB.
 */
class LedgerEntry extends Model
{
    use HasUuids;

    protected $table = 'ledger_entries';

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';

    protected $fillable = ['ledger_transaction_id', 'ledger_account_id', 'type', 'amount_cents', 'currency', 'memo'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
