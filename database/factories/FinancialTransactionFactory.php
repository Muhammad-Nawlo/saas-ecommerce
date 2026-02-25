<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financial\FinancialOrder;
use App\Models\Financial\FinancialTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    protected $model = FinancialTransaction::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(2000, 50000);
        return [
            'tenant_id' => (string) Str::uuid(),
            'order_id' => FinancialOrder::factory(),
            'type' => FinancialTransaction::TYPE_CREDIT,
            'amount_cents' => $amount,
            'currency' => 'USD',
            'provider_reference' => 'pi_' . Str::random(24),
            'status' => FinancialTransaction::STATUS_COMPLETED,
            'meta' => ['event' => 'order_paid'],
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FinancialTransaction::TYPE_REFUND,
            'meta' => ['event' => 'order_refunded'],
        ]);
    }
}
