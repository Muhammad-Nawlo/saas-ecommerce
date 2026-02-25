<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentModel>
 */
class PaymentModelFactory extends Factory
{
    protected $model = PaymentModel::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(2000, 100000);
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) Str::uuid(),
            'order_id' => (string) Str::uuid(),
            'amount' => $amount,
            'currency' => 'USD',
            'status' => PaymentModel::STATUS_SUCCEEDED,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'payment_currency' => 'USD',
            'payment_amount' => $amount,
            'exchange_rate_snapshot' => [
                'base_code' => 'USD',
                'target_code' => 'USD',
                'rate' => 1.0,
                'source' => 'seed',
                'effective_at' => now()->toIso8601String(),
            ],
            'payment_amount_base' => $amount,
            'snapshot_hash' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }
}
