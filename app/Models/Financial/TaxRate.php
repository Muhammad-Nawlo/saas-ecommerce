<?php

declare(strict_types=1);

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property float $percentage
 * @property string $country_code
 * @property string|null $region_code
 * @property bool $is_active
 */
class TaxRate extends Model
{
    use HasUuids;

    protected $table = 'tax_rates';

    protected $fillable = [
        'tenant_id',
        'name',
        'percentage',
        'country_code',
        'region_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
