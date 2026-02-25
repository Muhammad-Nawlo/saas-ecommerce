<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Cart\Infrastructure\Persistence\CartModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CartModel>
 */
class CartModelFactory extends Factory
{
    protected $model = CartModel::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'customer_email' => fake()->safeEmail(),
            'session_id' => Str::random(40),
            'status' => 'active',
            'total_amount' => 0,
            'currency' => 'USD',
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }
}
