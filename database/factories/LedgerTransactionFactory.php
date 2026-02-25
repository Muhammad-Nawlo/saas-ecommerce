<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ledger\Ledger;
use App\Models\Ledger\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    protected $model = LedgerTransaction::class;

    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'reference_type' => 'financial_order',
            'reference_id' => (string) \Illuminate\Support\Str::uuid(),
            'description' => fake()->sentence(),
            'transaction_at' => now(),
        ];
    }
}
