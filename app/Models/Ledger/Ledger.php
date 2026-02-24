<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ledger (one per tenant). Holds accounts and transactions. Entries are immutable.
 */
class Ledger extends Model
{
    use HasUuids;

    protected $table = 'ledgers';

    protected $fillable = ['tenant_id', 'name', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }
}
