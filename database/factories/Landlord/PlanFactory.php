<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Basic', 'Pro', 'Enterprise', 'Starter']);
        $price = fake()->randomFloat(2, 29, 199);
        return [
            'name' => $name,
            'code' => strtolower($name),
            'price' => $price,
            'price_amount' => (int) round($price * 100),
            'currency' => 'USD',
            'billing_interval' => fake()->randomElement(['month', 'year']),
            'stripe_price_id' => 'price_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
