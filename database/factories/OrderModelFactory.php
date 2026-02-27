<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Orders\Infrastructure\Persistence\OrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderModel>
 */
class OrderModelFactory extends Factory
{
    protected $model = OrderModel::class;

    public function definition(): array
    {
        $totalAmount = fake()->numberBetween(2000, 100000);
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'user_id' => null,
            'customer_email' => fake()->safeEmail(),
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'currency' => 'USD',
            'discount_total_cents' => 0,
            'applied_promotions' => null,
            'internal_notes' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paid']);
    }
}
