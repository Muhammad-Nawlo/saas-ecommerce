<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds common ISO 4217 currencies. Run in tenant context after currencies table exists.
 */
class CurrencySeeder extends Seeder
{
    protected array $currencies = [
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2],
        ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'decimal_places' => 2],
        ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'decimal_places' => 2],
        ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2],
    ];

    public function run(): void
    {
        $now = now();
        foreach ($this->currencies as $c) {
            DB::table('currencies')->insertOrIgnore([
                'code' => $c['code'],
                'name' => $c['name'],
                'symbol' => $c['symbol'],
                'decimal_places' => $c['decimal_places'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
