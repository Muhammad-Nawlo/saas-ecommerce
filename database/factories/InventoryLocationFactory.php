<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inventory\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    protected $model = InventoryLocation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'name' => fake()->city() . ' Warehouse',
            'code' => strtoupper(fake()->unique()->lexify('WH???')),
            'type' => fake()->randomElement([
                InventoryLocation::TYPE_WAREHOUSE,
                InventoryLocation::TYPE_RETAIL_STORE,
                InventoryLocation::TYPE_FULFILLMENT_CENTER,
            ]),
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->countryCode(),
            ],
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }
}
