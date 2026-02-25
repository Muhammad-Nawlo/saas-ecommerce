<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financial\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'name' => 'VAT',
            'percentage' => 10.00,
            'country_code' => 'US',
            'region_code' => null,
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }
}
