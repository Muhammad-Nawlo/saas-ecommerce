<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single ledger entry (debit or credit). Immutable.
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
