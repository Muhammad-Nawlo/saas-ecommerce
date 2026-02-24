<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ledger transaction. Must be balanced (sum debits = sum credits). Immutable once created.
 */
class LedgerTransaction extends Model
{
    use HasUuids;

    protected $table = 'ledger_transactions';

    protected $fillable = ['ledger_id', 'reference_type', 'reference_id', 'description', 'transaction_at'];

    protected function casts(): array
    {
        return ['transaction_at' => 'datetime'];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_transaction_id');
    }
}
