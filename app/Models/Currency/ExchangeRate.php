<?php

declare(strict_types=1);

namespace App\Models\Currency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable rate snapshot. One row per (base, target, effective_at).
 *
 * @property int $id
 * @property int $base_currency_id
 * @property int $target_currency_id
 * @property float $rate
 * @property string $source
 * @property \Illuminate\Support\Carbon $effective_at
 */
class ExchangeRate extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'exchange_rates';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API = 'api';

    protected $fillable = [
        'base_currency_id',
        'target_currency_id',
        'rate',
        'source',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'effective_at' => 'datetime',
        ];
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function targetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency_id');
    }

    /** Return immutable snapshot for storage (e.g. in order). */
    public function toSnapshot(): array
    {
        return [
            'base_currency_id' => $this->base_currency_id,
            'target_currency_id' => $this->target_currency_id,
            'base_code' => $this->baseCurrency?->code,
            'target_code' => $this->targetCurrency?->code,
            'rate' => $this->rate,
            'source' => $this->source,
            'effective_at' => $this->effective_at->toIso8601String(),
        ];
    }
}
