<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = fake()->unique()->currencyCode();
        return [
            'code' => $code,
            'name' => $code . ' Currency',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);
    }

    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'decimal_places' => 2,
        ]);
    }
}
