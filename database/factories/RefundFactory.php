<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financial\FinancialOrder;
use App\Models\Refund\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 20000);
        return [
            'tenant_id' => (string) Str::uuid(),
            'financial_order_id' => FinancialOrder::factory(),
            'amount_cents' => $amount,
            'currency' => 'USD',
            'reason' => fake()->sentence(),
            'status' => Refund::STATUS_COMPLETED,
            'payment_reference' => 'ref_' . Str::random(16),
            'financial_transaction_id' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }
}
