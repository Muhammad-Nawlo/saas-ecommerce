<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ledger\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ledger>
 */
class LedgerFactory extends Factory
{
    protected $model = Ledger::class;

    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'name' => 'Default',
            'currency' => 'USD',
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }
}
