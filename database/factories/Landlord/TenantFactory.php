<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numerify('###'),
            'status' => 'active',
            'plan_id' => null,
            'stripe_customer_id' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);
    }
}
