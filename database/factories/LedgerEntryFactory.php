<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ledger\LedgerAccount;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 50000);
        return [
            'ledger_transaction_id' => LedgerTransaction::factory(),
            'ledger_account_id' => LedgerAccount::factory(),
            'type' => LedgerEntry::TYPE_DEBIT,
            'amount_cents' => $amount,
            'currency' => 'USD',
            'memo' => fake()->sentence(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => ['type' => LedgerEntry::TYPE_CREDIT]);
    }
}
