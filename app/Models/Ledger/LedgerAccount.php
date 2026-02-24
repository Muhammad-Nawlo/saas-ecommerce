<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ledger account (Revenue, Tax payable, Cash, etc.). Type determines normal balance.
 */
class LedgerAccount extends Model
{
    use HasUuids;

    protected $table = 'ledger_accounts';

    public const TYPE_REVENUE = 'revenue';
    public const TYPE_TAX_PAYABLE = 'tax_payable';
    public const TYPE_PLATFORM_COMMISSION = 'platform_commission';
    public const TYPE_ACCOUNTS_RECEIVABLE = 'accounts_receivable';
    public const TYPE_CASH = 'cash';
    public const TYPE_REFUND_LIABILITY = 'refund_liability';

    protected $fillable = ['ledger_id', 'code', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id');
    }
}
