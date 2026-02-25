<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Promotion\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $startsAt = now()->subDays(7);
        $endsAt = now()->addMonths(2);
        return [
            'tenant_id' => (string) Str::uuid(),
            'name' => fake()->words(3, true) . ' Offer',
            'type' => Promotion::TYPE_PERCENTAGE,
            'value_cents' => 0,
            'percentage' => 10.0,
            'min_cart_cents' => 5000,
            'buy_quantity' => null,
            'get_quantity' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_stackable' => false,
            'max_uses_total' => 1000,
            'max_uses_per_customer' => 1,
            'is_active' => true,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Promotion::TYPE_FIXED,
            'value_cents' => 1000,
            'percentage' => null,
        ]);
    }
}
