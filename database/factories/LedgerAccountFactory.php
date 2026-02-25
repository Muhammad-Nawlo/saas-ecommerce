<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ledger\Ledger;
use App\Models\Ledger\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'code' => fake()->unique()->regexify('[A-Z]{2,4}'),
            'name' => fake()->words(2, true),
            'type' => LedgerAccount::TYPE_REVENUE,
            'is_active' => true,
        ];
    }
}
