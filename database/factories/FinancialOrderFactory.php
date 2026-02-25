<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financial\FinancialOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialOrder>
 */
class FinancialOrderFactory extends Factory
{
    protected $model = FinancialOrder::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(2000, 80000);
        $taxTotal = (int) round($subtotal * 0.1);
        $discount = 0;
        $total = $subtotal + $taxTotal - $discount;
        return [
            'operational_order_id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'property_id' => null,
            'order_number' => 'FIN-' . Str::uuid(),
            'subtotal_cents' => $subtotal,
            'tax_total_cents' => $taxTotal,
            'discount_total_cents' => $discount,
            'total_cents' => $total,
            'currency' => 'USD',
            'base_currency' => 'USD',
            'display_currency' => 'USD',
            'exchange_rate_snapshot' => null,
            'subtotal_base_cents' => $subtotal,
            'subtotal_display_cents' => $subtotal,
            'tax_base_cents' => $taxTotal,
            'tax_display_cents' => $taxTotal,
            'total_base_cents' => $total,
            'total_display_cents' => $total,
            'status' => FinancialOrder::STATUS_DRAFT,
            'snapshot' => null,
            'snapshot_hash' => null,
            'locked_at' => null,
            'paid_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FinancialOrder::STATUS_PENDING,
            'locked_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FinancialOrder::STATUS_PAID,
            'locked_at' => now(),
            'paid_at' => now(),
        ]);
    }
}
