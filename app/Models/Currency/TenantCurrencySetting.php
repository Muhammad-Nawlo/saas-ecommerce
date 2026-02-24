<?php

declare(strict_types=1);

namespace App\Models\Currency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $base_currency_id
 * @property bool $allow_multi_currency
 * @property string $rounding_strategy
 */
class TenantCurrencySetting extends Model
{
    protected $table = 'tenant_currency_settings';

    public const ROUNDING_BANKERS = 'bankers';
    public const ROUNDING_HALF_UP = 'half_up';
    public const ROUNDING_HALF_DOWN = 'half_down';

    protected $fillable = [
        'tenant_id',
        'base_currency_id',
        'allow_multi_currency',
        'rounding_strategy',
    ];

    protected function casts(): array
    {
        return [
            'allow_multi_currency' => 'boolean',
        ];
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }
}
