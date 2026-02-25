<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Persistence\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductModel>
 */
class ProductModelFactory extends Factory
{
    protected $model = ProductModel::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);
        $priceCents = fake()->numberBetween(999, 99999); // 9.99 to 999.99
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'price_minor_units' => $priceCents,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
