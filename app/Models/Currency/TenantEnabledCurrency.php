<?php

declare(strict_types=1);

namespace App\Models\Currency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: currencies enabled for selling when tenant has allow_multi_currency.
 *
 * @property int $tenant_id
 * @property int $currency_id
 */
class TenantEnabledCurrency extends Model
{
    protected $table = 'tenant_enabled_currencies';

    protected $fillable = ['tenant_id', 'currency_id'];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
